<?php
/**
 * security.php — MPANGO LOUNGE
 * -------------------------------------------------------
 * Central security layer:
 *   - CSRF protection (auto + per-form)
 *   - Session hardening (fingerprint, timeout, regeneration)
 *   - Login rate limiting
 *   - Security headers (CSP, X-Frame-Options, etc.)
 *   - Suspicious activity logging
 *   - Ownership checks
 * -------------------------------------------------------
 */

class Security
{
    // ============================================================
    // 1. CSRF PROTECTION
    // ============================================================

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifyCsrf(?string $submitted = null): bool
    {
        $submitted = $submitted ?? ($_POST['csrf_token'] ?? '');
        if (empty($_SESSION['csrf_token']) || empty($submitted)) return false;
        return hash_equals($_SESSION['csrf_token'], $submitted);
    }

    public static function verifyCsrfOrFail(): void
    {
        if (!self::verifyCsrf()) {
            self::logSuspicious('csrf.fail',
                'CSRF token invalid for ' . ($_SERVER['REQUEST_URI'] ?? '?'));
            http_response_code(403);
            die('Invalid security token. Refresh the page and try again.');
        }
    }

    // ============================================================
    // 2. SESSION HARDENING
    // ============================================================

    /** Regenerate session ID — call this on login to prevent session fixation. */
    public static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
            $_SESSION['fingerprint'] = self::sessionFingerprint();
            $_SESSION['last_activity'] = time();
        }
    }

    public static function sessionFingerprint(): string
    {
        // UA-only — IP changes on mobile networks so we don't bind to it.
        // Combined with a server secret so it can't be forged.
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $secret = defined('SESSION_FINGERPRINT_SECRET') ? SESSION_FINGERPRINT_SECRET : 'mpango-default-secret';
        return hash('sha256', $ua . '|' . $secret);
    }

    public static function checkSessionFingerprint(): bool
    {
        $current = self::sessionFingerprint();
        if (empty($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $current;
            return true;
        }
        return hash_equals($_SESSION['fingerprint'], $current);
    }

    /**
     * Auto-logout if session idle too long.
     * Default 8 hours. Call on every protected page load.
     */
    public static function sessionTimeout(int $maxSeconds = 28800): void
    {
        if (empty($_SESSION['logged_in'])) return;

        // Fingerprint check — catch stolen sessions
        if (!self::checkSessionFingerprint()) {
            self::logSuspicious('session.fingerprint_mismatch',
                'Session fingerprint mismatch for user #' . ($_SESSION['user_id'] ?? '?'));
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'login.php?error='
                . urlencode('Session invalidated. Please log in again.'));
            exit;
        }

        $last = $_SESSION['last_activity'] ?? time();
        if (time() - $last > $maxSeconds) {
            self::logSuspicious('session.timeout',
                'Session timed out for user #' . ($_SESSION['user_id'] ?? '?'));
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'login.php?error='
                . urlencode('Session expired. Please log in again.'));
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    // ============================================================
    // 3. SECURITY HEADERS
    // ============================================================

    public static function setHeaders(): void
    {
        if (headers_sent()) return;

        // CSP — allows our CDNs but blocks everything else (stops XSS)
        header("Content-Security-Policy: "
             . "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
             . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
             . "img-src 'self' data: https:; "
             . "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; "
             . "connect-src 'self'; "
             . "frame-ancestors 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self';");

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('X-XSS-Protection: 1; mode=block');

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    // ============================================================
    // 4. LOGIN RATE LIMITING
    // ============================================================

    /**
     * Check if login attempt is allowed. Returns:
     *  ['allowed' => bool, 'retry_after' => int seconds, 'attempts' => int]
     */
    public static function checkLoginRateLimit(string $username, string $ip): array
    {
        $maxAttempts = 5;
        $windowSecs  = 900;   // 15 minutes

        try {
            $db = Database::getConnection();

            // Cleanup old entries (older than 1 day)
            $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)")
               ->execute();

            // Count failures in window
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE (username = :u OR ip_address = :ip)
                   AND success = 0
                   AND attempted_at >= DATE_SUB(NOW(), INTERVAL :w SECOND)"
            );
            $stmt->execute(['u' => $username, 'ip' => $ip, 'w' => $windowSecs]);
            $count = (int) $stmt->fetchColumn();

            if ($count >= $maxAttempts) {
                $stmt = $db->prepare(
                    "SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(attempted_at, INTERVAL :w SECOND)) AS wait_secs
                     FROM login_attempts
                     WHERE (username = :u OR ip_address = :ip) AND success = 0
                     ORDER BY attempted_at ASC LIMIT 1"
                );
                $stmt->execute(['u' => $username, 'ip' => $ip, 'w' => $windowSecs]);
                $retryAfter = max(1, (int) $stmt->fetchColumn());
                return ['allowed' => false, 'retry_after' => $retryAfter, 'attempts' => $count];
            }
            return ['allowed' => true, 'retry_after' => 0, 'attempts' => $count];
        } catch (Throwable $e) {
            // Fail open — don't lock everyone out if DB has an issue
            return ['allowed' => true, 'retry_after' => 0, 'attempts' => 0];
        }
    }

    public static function recordLoginAttempt(string $username, string $ip, bool $success): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO login_attempts (username, ip_address, success) VALUES (:u, :ip, :s)"
            );
            $stmt->execute(['u' => $username, 'ip' => $ip, 's' => $success ? 1 : 0]);

            if ($success) {
                // Successful login wipes prior failures for that username
                $db->prepare("DELETE FROM login_attempts WHERE username = :u")->execute(['u' => $username]);
            }
        } catch (Throwable $e) {}
    }

    // ============================================================
    // 5. SUSPICIOUS ACTIVITY LOGGING
    // ============================================================

    public static function logSuspicious(string $action, string $details): void
    {
        try {
            if (class_exists('AuditLog')) {
                AuditLog::record($action, null, null, $details);
            }
        } catch (Throwable $e) {}
    }

    // ============================================================
    // 6. OWNERSHIP / ACCESS CONTROL
    // ============================================================

    /** Allow if admin OR if resource belongs to current user. Otherwise 403. */
    public static function requireOwnershipOrAdmin(int $ownerId): void
    {
        $currentUid = (int)($_SESSION['user_id'] ?? 0);
        if (!User::isAdmin() && $currentUid !== $ownerId) {
            self::logSuspicious('idor.attempt',
                'User #' . $currentUid . ' tried to access resource of user #' . $ownerId);
            http_response_code(403);
            die('Access denied.');
        }
    }

    // ============================================================
    // 7. SAFE INPUT HELPERS
    // ============================================================

    public static function safeInt($v, int $default = 0): int
    {
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function safeFloat($v, float $default = 0.0): float
    {
        return is_numeric($v) ? (float)$v : $default;
    }

    public static function safeDate($v, ?string $default = null): string
    {
        if (!is_string($v) || trim($v) === '') return $default ?? date('Y-m-d');
        $ts = strtotime($v);
        if ($ts === false) return $default ?? date('Y-m-d');
        if ($ts < strtotime('-5 years') || $ts > strtotime('+5 years')) return $default ?? date('Y-m-d');
        return date('Y-m-d', $ts);
    }

    /** Prevents worker from passing a future date on sales */
    public static function safePastDate($v, int $allowFutureDays = 0): string
    {
        $d = self::safeDate($v);
        $max = date('Y-m-d', strtotime('+' . $allowFutureDays . ' days'));
        return $d > $max ? date('Y-m-d') : $d;
    }

    // ============================================================
    // 8. GLOBAL BOOT (called from config.php)
    // ============================================================

    public static function boot(): void
    {
        // Set headers on every request
        self::setHeaders();

        // Generate CSRF token for the session
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::csrfToken();
        }

        // Auto-check timeout on logged-in users
        self::sessionTimeout();

        // Reject POST without valid CSRF (except login + logout)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentFile = basename($_SERVER['PHP_SELF'] ?? '');
            $exempt = ['login.php', 'logout.php'];
            if (!in_array($currentFile, $exempt, true)) {
                self::verifyCsrfOrFail();
            }
        }
    }
}