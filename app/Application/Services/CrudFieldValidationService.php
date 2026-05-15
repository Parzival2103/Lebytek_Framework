<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Exceptions\ValidationException;

/**
 * Separación explícita: sanitización (limpieza) → normalización (forma canónica) → validación (reglas).
 * No se convierten silenciosamente valores inválidos: validateValue debe fallar antes de toStorageValue().
 */
final class CrudFieldValidationService
{
    private const REGEX_PATTERN_MAX_LENGTH = 512;

    /**
     * Limpieza estructural sin aplicar reglas de negocio ni tipos estrictos.
     */
    public function sanitizeRawInput(CrudFieldDefinition $field, mixed $raw): mixed
    {
        if ($field->type() === 'file') {
            return $raw;
        }

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $value = trim($raw);
            $rules = $field->validation();
            $allowHtml = is_array($rules) && !empty($rules['allow_html']);
            if (!$allowHtml && in_array($field->type(), ['text', 'textarea', 'hidden', 'select'], true)) {
                $value = strip_tags($value);
            }

            $effectiveType = $this->effectiveValidationType($field);
            if ($effectiveType === 'email' || $field->type() === 'email') {
                $value = strtolower($value);
            }

            return $value;
        }

        if (is_int($raw) || is_float($raw)) {
            return $raw;
        }

        return (string) $raw;
    }

    /**
     * Forma canónica para validar (sin forzar tipos inválidos).
     */
    public function normalizeValue(CrudFieldDefinition $field, mixed $sanitized): mixed
    {
        if ($field->type() === 'file') {
            return $sanitized;
        }

        if ($field->type() === 'checkbox') {
            if ($sanitized === null || $sanitized === '') {
                return 0;
            }
            if ($sanitized === true || $sanitized === 1 || $sanitized === '1') {
                return 1;
            }
            if ($sanitized === false || $sanitized === 0 || $sanitized === '0') {
                return 0;
            }
            if (is_string($sanitized)) {
                $v = strtolower(trim($sanitized));
                if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
                    return 1;
                }
                if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) {
                    return 0;
                }

                return $sanitized;
            }

            return $sanitized;
        }

        $effectiveType = $this->effectiveValidationType($field);
        if ($effectiveType === 'boolean' || $effectiveType === 'bool') {
            if ($sanitized === null || $sanitized === '') {
                return null;
            }
            if (is_bool($sanitized)) {
                return $sanitized ? 1 : 0;
            }
            if (is_int($sanitized) || is_float($sanitized)) {
                return ((int) $sanitized) === 1 ? 1 : 0;
            }
            if (is_string($sanitized)) {
                $v = strtolower(trim($sanitized));
                if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
                    return 1;
                }
                if (in_array($v, ['0', 'false', 'no', 'off'], true)) {
                    return 0;
                }
            }

            return $sanitized;
        }

        return $sanitized;
    }

    /**
     * @return list<string>
     */
    public function validateValue(CrudFieldDefinition $field, mixed $normalized): array
    {
        $errors = [];
        $rules = $field->validation();
        if (!is_array($rules)) {
            $rules = [];
        }

        $required = (bool) ($rules['required'] ?? $field->required());
        $effectiveType = $this->effectiveValidationType($field);

        if ($field->type() === 'checkbox') {
            if ($normalized !== 0 && $normalized !== 1) {
                $errors[] = 'Valor de casilla inválido.';

                return $errors;
            }
            if ($required && $normalized !== 1) {
                $errors[] = 'Debe marcar esta opción.';
            }

            return $errors;
        }

        if ($required && ($normalized === null || $normalized === '')) {
            $errors[] = 'Este campo es obligatorio.';

            return $errors;
        }

        if ($normalized === null || $normalized === '') {
            return $errors;
        }

        if (isset($rules['minlength']) && is_string($normalized) && mb_strlen($normalized) < (int) $rules['minlength']) {
            $errors[] = 'Longitud mínima no cumplida.';
        }
        if (isset($rules['maxlength']) && is_string($normalized) && mb_strlen($normalized) > (int) $rules['maxlength']) {
            $errors[] = 'Longitud máxima excedida.';
        }

        if ($effectiveType === 'email' || $field->type() === 'email') {
            if (!is_string($normalized) || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Correo electrónico inválido.';
            }
        }

        if ($effectiveType === 'integer' || $effectiveType === 'int') {
            if (is_float($normalized)) {
                $errors[] = 'Debe ser un número entero válido.';
            } else {
                $asString = is_int($normalized) ? (string) $normalized : trim((string) $normalized);
                if (!$this->isStrictIntegerString($asString)) {
                    $errors[] = 'Debe ser un número entero válido.';
                } else {
                    $intVal = (int) $asString;
                    if (isset($rules['min']) && $intVal < (int) $rules['min']) {
                        $errors[] = 'Valor menor al mínimo permitido.';
                    }
                    if (isset($rules['max']) && $intVal > (int) $rules['max']) {
                        $errors[] = 'Valor mayor al máximo permitido.';
                    }
                }
            }
        }

        if ($effectiveType === 'numeric' || $effectiveType === 'decimal' || $effectiveType === 'money') {
            if (!is_string($normalized) && !is_int($normalized) && !is_float($normalized)) {
                $errors[] = 'Debe ser un valor numérico.';
            } elseif (!$this->isStrictDecimal((string) $normalized)) {
                $errors[] = 'Formato numérico inválido.';
            } else {
                $floatVal = (float) str_replace(',', '.', (string) $normalized);
                if (!is_finite($floatVal)) {
                    $errors[] = 'Valor numérico no permitido.';
                } else {
                    if (isset($rules['min']) && $floatVal < (float) $rules['min']) {
                        $errors[] = 'Valor menor al mínimo permitido.';
                    }
                    if (isset($rules['max']) && $floatVal > (float) $rules['max']) {
                        $errors[] = 'Valor mayor al máximo permitido.';
                    }
                }
            }
        }

        if ($effectiveType === 'string' || $effectiveType === 'text') {
            if (!is_string($normalized) && !is_numeric($normalized)) {
                $errors[] = 'Debe ser texto.';
            }
        }

        if ($effectiveType === 'boolean' || $effectiveType === 'bool') {
            if (!is_int($normalized) || ($normalized !== 0 && $normalized !== 1)) {
                $errors[] = 'Valor booleano inválido.';
            }
        }

        if ($effectiveType === 'date') {
            if (!is_string($normalized) || !$this->isValidDateYmd($normalized)) {
                $errors[] = 'Fecha inválida. Use el formato AAAA-MM-DD.';
            }
        }

        if ($effectiveType === 'datetime') {
            if (!is_string($normalized) || !$this->isValidDateTime($normalized)) {
                $errors[] = 'Fecha y hora inválidas.';
            }
        }

        if (isset($rules['in']) && is_array($rules['in'])) {
            if (!in_array((string) $normalized, array_map('strval', $rules['in']), true)) {
                $errors[] = 'Valor no permitido.';
            }
        }

        if (isset($rules['regex']) && is_string($rules['regex'])) {
            $pattern = $rules['regex'];
            if (!$this->isSafeRegexPattern($pattern)) {
                $errors[] = 'Regla de formato no disponible.';
            } elseif (is_string($normalized) && preg_match($pattern, $normalized) !== 1) {
                $errors[] = 'Formato inválido.';
            }
        }

        return $errors;
    }

    /**
     * @param iterable<CrudFieldDefinition> $fields
     * @param array<string, mixed> $normalizedByField
     * @return array<string, list<string>>
     */
    public function validatePayload(iterable $fields, array $normalizedByField): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }
            if ($field->type() === 'file' || $field->readonly()) {
                continue;
            }
            $name = $field->name();
            if (!array_key_exists($name, $normalizedByField)) {
                continue;
            }
            foreach ($this->validateValue($field, $normalizedByField[$name]) as $msg) {
                $errors[$name][] = $msg;
            }
        }

        return $errors;
    }

    /**
     * Convierte valor ya validado a tipos persistibles (int/float/string). No corregir aquí.
     *
     * @throws \InvalidArgumentException
     */
    public function toStorageValue(CrudFieldDefinition $field, mixed $normalized): mixed
    {
        $effectiveType = $this->effectiveValidationType($field);

        if ($field->type() === 'checkbox') {
            return (int) $normalized === 1 ? 1 : 0;
        }

        if ($normalized === null) {
            return null;
        }

        if ($normalized === '') {
            return '';
        }

        if ($effectiveType === 'integer' || $effectiveType === 'int') {
            return (int) $normalized;
        }

        if ($effectiveType === 'numeric' || $effectiveType === 'decimal' || $effectiveType === 'money') {
            $s = str_replace(',', '.', (string) $normalized);

            return (float) $s;
        }

        if ($effectiveType === 'boolean' || $effectiveType === 'bool') {
            return (int) $normalized === 1 ? 1 : 0;
        }

        return $normalized;
    }

    /**
     * @deprecated Usar sanitizeRawInput → normalizeValue → validateValue → toStorageValue
     * @return array<string, list<string>>
     */
    public function validateFieldValue(CrudFieldDefinition $field, mixed $rawValue): array
    {
        $sanitized = $this->sanitizeRawInput($field, $rawValue);
        $normalized = $this->normalizeValue($field, $sanitized);

        return $this->validateValue($field, $normalized);
    }

    /**
     * @deprecated Usar toStorageValue tras validar
     */
    public function coerceValue(CrudFieldDefinition $field, mixed $rawValue): mixed
    {
        $sanitized = $this->sanitizeRawInput($field, $rawValue);
        $normalized = $this->normalizeValue($field, $sanitized);
        $errors = $this->validateValue($field, $normalized);
        if ($errors !== []) {
            throw new \InvalidArgumentException('invalid');
        }

        return $this->toStorageValue($field, $normalized);
    }

    /**
     * @param array<string, list<string>> $errorsByField
     *
     * @throws ValidationException
     */
    public function assertNoErrors(array $errorsByField): void
    {
        $flat = [];
        foreach ($errorsByField as $field => $errs) {
            foreach ($errs as $e) {
                $flat[] = "{$field}: {$e}";
            }
        }
        if (!empty($flat)) {
            throw new ValidationException('Hay errores en el formulario.', $errorsByField);
        }
    }

    private function effectiveValidationType(CrudFieldDefinition $field): string
    {
        $rules = $field->validation();
        if (is_array($rules) && isset($rules['type']) && is_string($rules['type']) && $rules['type'] !== '') {
            return strtolower($rules['type']);
        }

        return strtolower($field->type());
    }

    private function isStrictIntegerString(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false) {
            return false;
        }

        return (string) $validated === $value;
    }

    private function isStrictDecimal(string $value): bool
    {
        if ($value === '' || str_contains(strtolower($value), 'e')) {
            return false;
        }

        $normalized = str_replace(',', '.', $value);
        if ($normalized === '' || $normalized === '.' || $normalized === '-' || $normalized === '-.') {
            return false;
        }

        return filter_var($normalized, FILTER_VALIDATE_FLOAT) !== false;
    }

    private function isValidDateYmd(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    private function isValidDateTime(string $value): bool
    {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $value);
            if ($dt !== false) {
                $errors = \DateTimeImmutable::getLastErrors();
                if (is_array($errors) && ($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isSafeRegexPattern(string $pattern): bool
    {
        if ($pattern === '' || str_contains($pattern, "\0")) {
            return false;
        }
        if (strlen($pattern) > self::REGEX_PATTERN_MAX_LENGTH) {
            return false;
        }
        if (str_contains($pattern, '(?R)') || str_contains($pattern, '(?r)')) {
            return false;
        }

        return @preg_match($pattern, '') !== false;
    }
}
