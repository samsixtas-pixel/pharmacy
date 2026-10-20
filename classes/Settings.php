<?php
/**
 * Settings.php — key/value app-wide settings (prices lock, default markup, etc).
 */
class Settings extends Model
{
    private static array $cache = [];

    protected function getTable(): string { return 'app_settings'; }

    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1");
        $stmt->execute(['k' => $key]);
        $v = $stmt->fetchColumn();
        return self::$cache[$key] = ($v === false ? $default : (string)$v);
    }

    public static function set(string $key, string $value): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v"
        );
        $ok = $stmt->execute(['k' => $key, 'v' => $value]);
        self::$cache[$key] = $value;
        return $ok;
    }

    public static function isPricesLocked(): bool
    {
        return self::get('prices_locked', '0') === '1';
    }
}