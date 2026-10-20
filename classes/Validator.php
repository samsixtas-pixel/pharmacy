<?php
/**
 * Validator.php
 * -------------------------------------------------------
 * Lightweight, reusable form validation helper.
 * Demonstrates: Encapsulation, static utility methods,
 * separation of validation logic from controllers/views.
 * -------------------------------------------------------
 */

class Validator
{
    private array $errors = [];

    public function required(mixed $value, string $field): self
    {
        if ($value === null || trim((string) $value) === '') {
            $this->errors[] = "{$field} is required.";
        }
        return $this;
    }

    public function numeric(mixed $value, string $field): self
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[] = "{$field} must be a number.";
        }
        return $this;
    }

    public function minValue(mixed $value, float $min, string $field): self
    {
        if ($value !== null && $value !== '' && is_numeric($value) && (float) $value < $min) {
            $this->errors[] = "{$field} must be at least {$min}.";
        }
        return $this;
    }

    public function maxLength(mixed $value, int $max, string $field): self
    {
        if ($value !== null) {
            $length = function_exists('mb_strlen') ? mb_strlen((string) $value) : strlen((string) $value);
            if ($length > $max) {
                $this->errors[] = "{$field} must not exceed {$max} characters.";
            }
        }
        return $this;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Sanitizes a string for safe HTML output (XSS protection).
     */
    public static function clean(?string $value): string
    {
        return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
