<?php

namespace App\Domain\Ai;

/**
 * Applies a versioned schema's field rules to decoded AI output (SRS FR-61).
 * Failures throw AiOutputException so nothing invalid is ever considered for
 * persistence.
 */
final class AiSchemaRules
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $fields
     */
    public function validate(array $data, array $fields): void
    {
        $this->validateFields($data, $fields, '$');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $fields
     */
    private function validateFields(array $data, array $fields, string $path): void
    {
        foreach ($fields as $key => $rules) {
            $rulePath = $path.'.'.$key;

            if (($rules['required'] ?? false) && ! array_key_exists($key, $data)) {
                throw AiOutputException::invalid("Missing required field {$rulePath}.");
            }

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $this->validateValue($data[$key], $rules, $rulePath);
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function validateValue(mixed $value, array $rules, string $path): void
    {
        $type = $rules['type'] ?? null;

        if ($type !== null && ! $this->matchesType($value, $type)) {
            throw AiOutputException::invalid("Field {$path} must be {$type}.");
        }

        if (isset($rules['enum']) && ! in_array($value, $rules['enum'], true)) {
            throw AiOutputException::invalid("Field {$path} must be one of: ".implode(', ', $rules['enum']).'.');
        }

        if (is_int($value)) {
            if (isset($rules['min']) && $value < $rules['min']) {
                throw AiOutputException::invalid("Field {$path} must be >= {$rules['min']}.");
            }
            if (isset($rules['max']) && $value > $rules['max']) {
                throw AiOutputException::invalid("Field {$path} must be <= {$rules['max']}.");
            }
        }

        if (is_string($value)) {
            if (isset($rules['max_length']) && mb_strlen($value) > $rules['max_length']) {
                throw AiOutputException::invalid("Field {$path} exceeds max length {$rules['max_length']}.");
            }
            if (isset($rules['pattern']) && preg_match($rules['pattern'], $value) !== 1) {
                throw AiOutputException::invalid("Field {$path} does not match its pattern.");
            }
            if (($rules['type'] ?? null) === 'date' && preg_match(self::DATE_PATTERN, $value) !== 1) {
                throw AiOutputException::invalid("Field {$path} must be a YYYY-MM-DD date.");
            }
        }

        if (is_array($value)) {
            $itemRules = $rules['items'] ?? null;

            if (isset($rules['min_items']) && count($value) < $rules['min_items']) {
                throw AiOutputException::invalid("Field {$path} must contain at least {$rules['min_items']} item(s).");
            }
            if (isset($rules['max_items']) && count($value) > $rules['max_items']) {
                throw AiOutputException::invalid("Field {$path} must contain at most {$rules['max_items']} item(s).");
            }

            if ($itemRules !== null && $value !== []) {
                $this->validateArrayItems($value, $itemRules, $path);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $itemRules
     */
    private function validateArrayItems(array $items, array $itemRules, string $path): void
    {
        $isObjectSchema = ! isset($itemRules['type']);

        foreach ($items as $index => $item) {
            $itemPath = $path."[{$index}]";

            if ($isObjectSchema) {
                if (! is_array($item)) {
                    throw AiOutputException::invalid("Field {$itemPath} must be an object.");
                }

                $this->validateFields($item, $itemRules, $itemPath);

                continue;
            }

            $this->validateValue($item, $itemRules, $itemPath);
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'array' => is_array($value),
            'bool' => is_bool($value),
            default => true,
        };
    }
}
