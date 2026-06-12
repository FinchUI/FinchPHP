<?php

/**
 * Finch\Validation\Validator - 声明式输入验证器
 */

declare(strict_types=1);

namespace Finch\Validation;

use Finch\App;
use InvalidArgumentException;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {
    }

    public function validate(): void
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            $ruleList = $this->normalizeRules($rules);

            foreach ($ruleList as $rule) {
                $this->applyRule($field, $value, $rule);
            }

            if (!isset($this->errors[$field]) && array_key_exists($field, $this->data)) {
                $this->validated[$field] = $this->data[$field];
            }
        }
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    /** @param string|list<string> $rules @return list<string> */
    private function normalizeRules(string|array $rules): array
    {
        return is_array($rules) ? array_values($rules) : explode('|', $rules);
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $argument] = array_pad(explode(':', $rule, 2), 2, '');

        if ($name !== 'required' && $this->isEmpty($value)) {
            return;
        }

        match ($name) {
            'required' => $this->validateRequired($field, $value),
            'max'      => $this->validateMax($field, $value, $argument),
            'min'      => $this->validateMin($field, $value, $argument),
            'email'    => $this->validateEmail($field, $value),
            'numeric'  => $this->validateNumeric($field, $value),
            'unique'   => $this->validateUnique($field, $value, $argument),
            'in'       => $this->validateIn($field, $value, $argument),
            'regex'    => $this->validateRegex($field, $value, $argument),
            default    => throw new InvalidArgumentException("未知验证规则：{$name}"),
        };
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            $this->addError($field, '该字段不能为空。');
        }
    }

    private function validateMax(string $field, mixed $value, string $argument): void
    {
        $max = (float) $argument;
        if ($this->size($value) > $max) {
            $this->addError($field, "该字段不能大于 {$argument}。");
        }
    }

    private function validateMin(string $field, mixed $value, string $argument): void
    {
        $min = (float) $argument;
        if ($this->size($value) < $min) {
            $this->addError($field, "该字段不能小于 {$argument}。");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, '邮箱格式不正确。');
        }
    }

    private function validateNumeric(string $field, mixed $value): void
    {
        if (!is_numeric($value)) {
            $this->addError($field, '该字段必须为数字。');
        }
    }

    private function validateUnique(string $field, mixed $value, string $argument): void
    {
        [$table, $column] = array_pad(explode(',', $argument, 2), 2, $field);
        $table = trim($table);
        $column = trim($column);

        if ($table === '' || $column === '') {
            throw new InvalidArgumentException('unique 规则需要 table,column 参数。');
        }

        $app = App::getInstance();
        if (!isset($app->db)) {
            return;
        }

        if ($app->db->table($table)->where($column, $value)->exists()) {
            $this->addError($field, '该字段已存在。');
        }
    }

    private function validateIn(string $field, mixed $value, string $argument): void
    {
        $allowed = array_map('trim', explode(',', $argument));
        if (!in_array((string) $value, $allowed, true)) {
            $this->addError($field, '该字段不在允许范围内。');
        }
    }

    private function validateRegex(string $field, mixed $value, string $argument): void
    {
        if (@preg_match($argument, '') === false) {
            throw new InvalidArgumentException('regex 规则参数不是有效正则表达式。');
        }

        if (!is_scalar($value) || preg_match($argument, (string) $value) !== 1) {
            $this->addError($field, '该字段格式不正确。');
        }
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function size(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        return (float) mb_strlen((string) $value);
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $message;
    }
}
