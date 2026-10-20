<?php
/**
 * User.php
 * -------------------------------------------------------
 * Manages user accounts and authentication.
 *
 * Demonstrates: Inheritance (extends Model), Encapsulation
 * (private credential properties), Polymorphism (overrides
 * getTable()).
 * -------------------------------------------------------
 */

class User extends Model
{
    private int $id;
    private string $fullName;
    private string $username;
    private string $role;
    private string $status;

    /**
     * Polymorphic override of the abstract parent method.
     */
    protected function getTable(): string
    {
        return 'users';
    }

    /**
     * Attempts to authenticate a user with username/password.
     * Uses password_verify() against the bcrypt hash — never
     * compares plaintext passwords.
     */
    public function login(string $username, string $password): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE username = :username AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }

        return false;
    }

    /**
     * Creates the authenticated session for a logged-in user.
     */
    public function createSession(array $user): void
    {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;

        AuditLog::record('auth.login', 'user', (int) $user['id'], 'Login as ' . $user['username'] . ' (' . $user['role'] . ')');
    }

    /**
     * Destroys the current session (logout).
     */
    public function destroySession(): void
    {
        AuditLog::record('auth.logout', 'user', (int) ($_SESSION['user_id'] ?? 0), 'User logged out');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain']);
        }
        session_destroy();
    }

    /**
     * Adds a new user with a securely hashed password.
     */
    public function addUser(string $fullName, string $username, string $password, string $role): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            "INSERT INTO users (full_name, username, password, role) VALUES (:full_name, :username, :password, :role)"
        );

        return $stmt->execute([
            'full_name' => $fullName,
            'username'  => $username,
            'password'  => $hash,
            'role'      => $role,
        ]);
    }

    /**
     * Updates an existing user. Password is optional —
     * if blank, the existing password hash is preserved.
     */
    public function updateUser(int $id, string $fullName, string $username, string $role, string $status, ?string $password = null): bool
    {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare(
                "UPDATE users SET full_name = :full_name, username = :username, role = :role, status = :status, password = :password WHERE id = :id"
            );
            return $stmt->execute([
                'full_name' => $fullName,
                'username'  => $username,
                'role'      => $role,
                'status'    => $status,
                'password'  => $hash,
                'id'        => $id,
            ]);
        }

        $stmt = $this->db->prepare(
            "UPDATE users SET full_name = :full_name, username = :username, role = :role, status = :status WHERE id = :id"
        );
        return $stmt->execute([
            'full_name' => $fullName,
            'username'  => $username,
            'role'      => $role,
            'status'    => $status,
            'id'        => $id,
        ]);
    }

    /**
     * Checks whether a username already exists (for validation),
     * excluding a specific user ID when editing.
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
        $params = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** ---------- Static helpers for Session/Auth checks ---------- */

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && $_SESSION['role'] === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
            exit;
        }
    }
    
    /** Persist a new TOTP secret and enable 2FA. */
    public function enableTotp(int $userId, string $secret, array $backupCodes): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET totp_secret = :s, totp_enabled = 1, totp_backup_codes = :b WHERE id = :id"
        );
        return $stmt->execute([
            's'  => $secret,
            'b'  => implode(',', $backupCodes),
            'id' => $userId,
        ]);
    }

    /** Disable 2FA for a user. */
    public function disableTotp(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_backup_codes = NULL WHERE id = :id"
        );
        return $stmt->execute(['id' => $userId]);
    }

    /** Consume a backup code (one-time use). */
    public function consumeBackupCode(int $userId, string $code): bool
    {
        $user = $this->find($userId);
        if (!$user || empty($user['totp_backup_codes'])) return false;

        $codes = array_map('trim', explode(',', $user['totp_backup_codes']));
        $code = strtoupper(trim($code));

        $idx = array_search($code, $codes, true);
        if ($idx === false) return false;

        unset($codes[$idx]);
        $stmt = $this->db->prepare("UPDATE users SET totp_backup_codes = :b WHERE id = :id");
        return $stmt->execute(['b' => implode(',', $codes), 'id' => $userId]);
    }

}
