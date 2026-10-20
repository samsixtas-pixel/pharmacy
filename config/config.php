<?php
/**
 * config.php — MPANGO LOUNGE
 * Global configuration + security bootstrap.
 */

// ============================================
// 1. ENVIRONMENT DETECTION
// ============================================
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
        || str_contains($_SERVER['HTTP_HOST'] ?? '', ':8000');

define('APP_ENV', $isLocal ? 'development' : 'production');

// ============================================
// 2. ERROR HANDLING
// ============================================
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // PRODUCTION: hide errors from users, log them instead
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    ini_set('error_log', $logDir . '/php_errors.log');
}

// ============================================
// 3. SESSION HARDENING
// ============================================
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '28800');   // 8 hours
ini_set('session.cookie_samesite', 'Lax');    // CSRF defense-in-depth

// HTTPS-only cookie when on HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 4. APP CONSTANTS
// ============================================
define('APP_NAME', 'MPANGO LOUNGE');
define('BASE_URL', '/');
define('LOW_STOCK_THRESHOLD', 20);
define('SESSION_FINGERPRINT_SECRET', 'change-this-to-a-long-random-string-once');

// ============================================
// 5. BOOT SECURITY (after session, before autoload.php uses classes)
// ============================================
require_once __DIR__ . '/../includes/security.php';