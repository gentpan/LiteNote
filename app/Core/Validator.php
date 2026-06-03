<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 数据验证器
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $errs) {
            return $errs[0] ?? null;
        }
        return null;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // rule:param 形式
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $param = $parts[1] ?? null;

        $valid = match ($name) {
            'required'  => $value !== null && $value !== '' && !(is_array($value) && empty($value)),
            'string'    => is_string($value) || is_numeric($value),
            'integer', 'int' => is_int($value) || (is_string($value) && ctype_digit((string)$value)),
            'numeric'   => is_numeric($value),
            'email'     => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'       => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'min'       => is_string($value) ? mb_strlen($value) >= (int)$param : (is_numeric($value) ? $value >= $param : false),
            'max'       => is_string($value) ? mb_strlen($value) <= (int)$param : (is_numeric($value) ? $value <= $param : false),
            'in'        => is_string($value) && in_array($value, explode(',', (string)$param), true),
            'regex'     => is_string($value) && preg_match($param, $value) === 1,
            'confirmed' => isset($this->data[$field . '_confirmation']) && $value === $this->data[$field . '_confirmation'],
            default     => true,
        };

        if (!$valid) {
            $this->errors[$field][] = self::message($name, $field, $param);
        }
    }

    private static function message(string $rule, string $field, ?string $param): string
    {
        return match ($rule) {
            'required'  => "{$field} 不能为空",
            'string'    => "{$field} 必须是字符串",
            'integer', 'int' => "{$field} 必须是整数",
            'numeric'   => "{$field} 必须是数字",
            'email'     => "{$field} 邮箱格式错误",
            'url'       => "{$field} URL 格式错误",
            'min'       => "{$field} 长度不能小于 {$param}",
            'max'       => "{$field} 长度不能大于 {$param}",
            'in'        => "{$field} 取值非法",
            'regex'     => "{$field} 格式错误",
            'confirmed' => "{$field} 两次输入不一致",
            default     => "{$field} 校验失败",
        };
    }
}
