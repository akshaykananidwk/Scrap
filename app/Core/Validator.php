<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rule-based validator with the Indian business formats this marketplace needs
 * (GSTIN, PAN, mobile, pincode, IFSC).
 *
 * Usage: $v = Validator::make($request->all(), ['email' => 'required|email']);
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    private function __construct(private array $data, private array $rules, private array $messages = [])
    {
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        $validator = new self($data, $rules, $messages);
        $validator->run();
        return $validator;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    /** Redirect back with errors + old input, or return JSON for AJAX callers. */
    public function failResponse(Request $request, ?string $redirectTo = null): Response
    {
        if ($request->wantsJson()) {
            return Response::json([
                'success' => false,
                'error' => $this->firstError(),
                'errors' => $this->errors,
            ], 422);
        }
        Session::flashErrors($this->errors);
        Session::flashInput($request->all());
        flash('danger', $this->firstError() ?? 'Please correct the highlighted fields.');
        return Response::redirect($redirectTo ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->value($field);
            $isNullable = in_array('nullable', $rules, true);

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if ($isNullable && $name !== 'required' && ($value === null || $value === '')) {
                    continue;
                }
                $this->apply($field, $name, $param, $value);
                if (isset($this->errors[$field])) {
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function value(string $field): mixed
    {
        $value = array_get($this->data, $field);
        return is_string($value) ? trim($value) : $value;
    }

    private function apply(string $field, string $rule, ?string $param, mixed $value): void
    {
        $label = $this->label($field);

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || $value === [] ) {
                    $this->addError($field, "{$label} is required.");
                }
                break;

            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->addError($field, "{$label} must be text.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "{$label} must be a number.");
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "{$label} must be a whole number.");
                }
                break;

            case 'decimal':
                if (!is_numeric($value)) {
                    $this->addError($field, "{$label} must be a valid amount.");
                }
                break;

            case 'min':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value < (float) $param) {
                        $this->addError($field, "{$label} must be at least {$param}.");
                    }
                } elseif (is_string($value) && mb_strlen($value) < (int) $param) {
                    $this->addError($field, "{$label} must be at least {$param} characters.");
                }
                break;

            case 'max':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value > (float) $param) {
                        $this->addError($field, "{$label} must not be greater than {$param}.");
                    }
                } elseif (is_string($value) && mb_strlen($value) > (int) $param) {
                    $this->addError($field, "{$label} must not exceed {$param} characters.");
                }
                break;

            case 'min_value':
                if (!is_numeric($value) || (float) $value < (float) $param) {
                    $this->addError($field, "{$label} must be at least {$param}.");
                }
                break;

            case 'max_value':
                if (!is_numeric($value) || (float) $value > (float) $param) {
                    $this->addError($field, "{$label} must not be greater than {$param}.");
                }
                break;

            case 'gt':
                if (!is_numeric($value) || (float) $value <= (float) $param) {
                    $this->addError($field, "{$label} must be greater than {$param}.");
                }
                break;

            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$label} must be a valid email address.");
                }
                break;

            case 'url':
                if (!filter_var((string) $value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$label} must be a valid URL.");
                }
                break;

            case 'mobile':
                if (!preg_match('/^[6-9]\d{9}$/', (string) $value)) {
                    $this->addError($field, "{$label} must be a valid 10-digit Indian mobile number.");
                }
                break;

            case 'gstin':
                if (!self::validGstin((string) $value)) {
                    $this->addError($field, "{$label} must be a valid 15-character GSTIN.");
                }
                break;

            case 'pan':
                if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper((string) $value))) {
                    $this->addError($field, "{$label} must be a valid PAN (e.g. ABCDE1234F).");
                }
                break;

            case 'pincode':
                if (!preg_match('/^[1-9][0-9]{5}$/', (string) $value)) {
                    $this->addError($field, "{$label} must be a valid 6-digit pincode.");
                }
                break;

            case 'ifsc':
                if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper((string) $value))) {
                    $this->addError($field, "{$label} must be a valid IFSC code.");
                }
                break;

            case 'vehicle':
                if (!preg_match('/^[A-Z]{2}[ -]?[0-9]{1,2}[ -]?[A-Z]{0,3}[ -]?[0-9]{1,4}$/i', (string) $value)) {
                    $this->addError($field, "{$label} must be a valid vehicle number.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->addError($field, "{$label} is not a valid selection.");
                }
                break;

            case 'boolean':
                if (!in_array($value, ['0', '1', 0, 1, true, false, 'on', null], true)) {
                    $this->addError($field, "{$label} must be true or false.");
                }
                break;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->addError($field, "{$label} must be a valid date.");
                }
                break;

            case 'after':
                $other = $this->value((string) $param) ?? $param;
                if (strtotime((string) $value) === false || strtotime((string) $value) <= strtotime((string) $other)) {
                    $this->addError($field, "{$label} must be after " . $this->label((string) $param) . '.');
                }
                break;

            case 'confirmed':
                if ($value !== ($this->data[$field . '_confirmation'] ?? null)) {
                    $this->addError($field, "{$label} confirmation does not match.");
                }
                break;

            case 'same':
                if ($value !== $this->value((string) $param)) {
                    $this->addError($field, "{$label} must match " . $this->label((string) $param) . '.');
                }
                break;

            case 'regex':
                if (!preg_match((string) $param, (string) $value)) {
                    $this->addError($field, "{$label} format is invalid.");
                }
                break;

            case 'alpha_dash':
                if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $value)) {
                    $this->addError($field, "{$label} may only contain letters, numbers, dashes and underscores.");
                }
                break;

            case 'password':
                if (strlen((string) $value) < 8
                    || !preg_match('/[A-Za-z]/', (string) $value)
                    || !preg_match('/[0-9]/', (string) $value)) {
                    $this->addError($field, "{$label} must be at least 8 characters and contain letters and numbers.");
                }
                break;

            case 'unique':
                // unique:table,column[,ignoreId]
                [$table, $column, $ignore] = array_pad(explode(',', (string) $param), 3, null);
                $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :v', $table, $column ?? $field);
                $params = ['v' => $value];
                if ($ignore !== null && $ignore !== '') {
                    $sql .= ' AND id <> :ignore';
                    $params['ignore'] = (int) $ignore;
                }
                if ((int) Database::instance()->scalar($sql, $params, 0) > 0) {
                    $this->addError($field, "This {$label} is already registered.");
                }
                break;

            case 'exists':
                // exists:table[,column]
                [$table, $column] = array_pad(explode(',', (string) $param), 2, 'id');
                $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :v', $table, $column);
                if ((int) Database::instance()->scalar($sql, ['v' => $value], 0) === 0) {
                    $this->addError($field, "The selected {$label} is invalid.");
                }
                break;

            default:
                break;
        }
    }

    public static function validGstin(string $gstin): bool
    {
        $gstin = strtoupper(trim($gstin));
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            return false;
        }
        // Checksum per GSTN specification (base-36 weighted modulus).
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $value = strpos($chars, $gstin[$i]);
            if ($value === false) {
                return false;
            }
            $factor = ($i % 2 === 0) ? 1 : 2;
            $product = $value * $factor;
            $sum += intdiv($product, 36) + ($product % 36);
        }
        $checksum = (36 - ($sum % 36)) % 36;
        return $gstin[14] === $chars[$checksum];
    }

    private function label(string $field): string
    {
        return $this->messages[$field] ?? ucwords(str_replace(['_', '.'], ' ', $field));
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
