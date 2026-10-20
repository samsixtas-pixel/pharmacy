<?php
/**
 * Totp.php — RFC 6238 TOTP (Google Authenticator compatible).
 * Pure PHP, no external libraries.
 */
class Totp
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a random base32 secret (16 chars = 80 bits). */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32[random_int(0, 31)];
        }
        return $secret;
    }

    /** Decode base32 to raw bytes. */
    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $buffer = 0;
        $bits = 0;
        $out = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $c = strpos(self::BASE32, $b32[$i]);
            if ($c === false) continue;
            $buffer = ($buffer << 5) | $c;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $out;
    }

    /** Compute the current 6-digit code for the given secret + time slice. */
    public static function code(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice = $timeSlice ?? (int)floor(time() / 30);
        $key = self::base32Decode($secret);

        $data = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $data, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ( ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Verify a code with ±1 window (±30s tolerance). */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;

        $slice = (int)floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(self::code($secret, $slice + $i), $code)) return true;
        }
        return false;
    }

    /** Build the otpauth:// URI for Google Authenticator QR. */
    public static function provisioningUri(string $secret, string $label, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
             . '?secret=' . $secret
             . '&issuer=' . rawurlencode($issuer)
             . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Generate N random backup codes (used if phone is lost). */
    public static function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}