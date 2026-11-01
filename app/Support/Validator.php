<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Input validation helper with fluent API.
 *
 * Usage:
 *   $validator = new Validator($inputData);
 *   $validator->required('email', 'Email')->email('email')->required('name', 'Name');
 *   if ($validator->fails()) {
 *       Response::error('VALIDATION_ERROR', 'Invalid input', 422, ['errors' => $validator->errors()]);
 *   }
 *   $clean = $validator->getInput();
 *
 * @package App\Support
 */
class Validator
{
    /**
     * The raw input data being validated.
     *
     * @var array
     */
    private array $data;

    /**
     * Accumulated validation errors keyed by field name.
     *
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * Create a new Validator instance.
     *
     * @param array $data The input data to validate.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate that a field is present and not empty.
     *
     * @param string      $field The field name to check.
     * @param string|null $label Human-readable label for error messages.
     * @return self
     */
    public function required(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;

        if (!isset($this->data[$field]) || (is_string($this->data[$field]) && trim($this->data[$field]) === '')) {
            $this->errors[$field] = "{$label} is required.";
        }

        return $this;
    }

    /**
     * Validate that a field is a string within length bounds.
     *
     * Skips validation if the field is not present (use required() to enforce presence).
     *
     * @param string $field The field name.
     * @param int    $min   Minimum string length (default 0).
     * @param int    $max   Maximum string length (default 255).
     * @return self
     */
    public function string(string $field, int $min = 0, int $max = 255): self
    {
        if (!isset($this->data[$field])) {
            return $this;
        }

        if (!is_string($this->data[$field])) {
            $this->errors[$field] = "{$field} must be a string.";
            return $this;
        }

        $length = mb_strlen($this->data[$field]);

        if ($length < $min) {
            $this->errors[$field] = "{$field} must be at least {$min} characters.";
        } elseif ($length > $max) {
            $this->errors[$field] = "{$field} must not exceed {$max} characters.";
        }

        return $this;
    }

    /**
     * Validate that a field is an integer (or numeric string representing an integer).
     *
     * Skips validation if the field is not present.
     *
     * @param string $field The field name.
     * @return self
     */
    public function integer(string $field): self
    {
        if (!isset($this->data[$field])) {
            return $this;
        }

        if (!is_numeric($this->data[$field]) || (int) $this->data[$field] != $this->data[$field]) {
            $this->errors[$field] = "{$field} must be an integer.";
        }

        return $this;
    }

    /**
     * Validate that a field is a valid email address.
     *
     * Skips validation if the field is not present.
     *
     * @param string $field The field name.
     * @return self
     */
    public function email(string $field): self
    {
        if (!isset($this->data[$field])) {
            return $this;
        }

        if (!is_string($this->data[$field]) || filter_var($this->data[$field], FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = "{$field} must be a valid email address.";
        }

        return $this;
    }

    /**
     * Validate that a field's value is within an allowed set.
     *
     * Skips validation if the field is not present.
     *
     * @param string $field   The field name.
     * @param array  $allowed Array of allowed values.
     * @return self
     */
    public function in(string $field, array $allowed): self
    {
        if (!isset($this->data[$field])) {
            return $this;
        }

        if (!in_array($this->data[$field], $allowed, true)) {
            $allowedList = implode(', ', $allowed);
            $this->errors[$field] = "{$field} must be one of: {$allowedList}.";
        }

        return $this;
    }

    /**
     * Validate that a field is a valid UUID (v4 format).
     *
     * Skips validation if the field is not present.
     *
     * @param string $field The field name.
     * @return self
     */
    public function uuid(string $field): self
    {
        if (!isset($this->data[$field])) {
            return $this;
        }

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        if (!is_string($this->data[$field]) || !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = "{$field} must be a valid UUID.";
        }

        return $this;
    }

    /**
     * Run validation and return errors array.
     *
     * @return array<string, string> Associative array of field => error message. Empty if valid.
     */
    public function validate(): array
    {
        return $this->errors;
    }

    /**
     * Check whether validation has failed.
     *
     * @return bool True if there are validation errors.
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the accumulated validation errors.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the sanitized input data.
     *
     * Trims all string values and returns the cleaned data array.
     *
     * @return array
     */
    public function getInput(): array
    {
        $sanitized = [];
        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
