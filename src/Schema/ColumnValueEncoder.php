<?php

declare(strict_types=1);

namespace Syde\Dbal\Schema;

/**
 * @psalm-consistent-constructor
 */
class ColumnValueEncoder
{
    private const CORE_MAYBE_SERIALIZED = [
        'blogmeta' => ['meta_value' => 1],
        'commentmeta' => ['meta_value' => 1],
        'options' => ['option_value' => 1],
        'postmeta' => ['meta_value' => 1],
        'signups' => ['meta' => 1],
        'sitemeta' => ['meta_value' => 1],
        'termmeta' => ['meta_value' => 1],
        'usermeta' => ['meta_value' => 1],
    ];

    private Column $column;
    private bool $coreMaybeSerialized = false;
    private bool $core = false;

    /**
     * @param Column $column
     * @param WpSchema $schema
     * @return static
     */
    public static function forCore(Column $column, WpSchema $schema): ColumnValueEncoder
    {
        $instance = new static($column);
        $instance->core = true;
        if (isset(self::CORE_MAYBE_SERIALIZED[$schema->name()][$column->name()])) {
            $instance->coreMaybeSerialized = true;
        }

        return $instance;
    }

    /**
     * @param Column $column
     * @return static
     */
    public static function for(Column $column): ColumnValueEncoder
    {
        return new static($column);
    }

    /**
     * @param Column $column
     */
    protected function __construct(Column $column)
    {
        $this->column = $column;
    }

    /**
     * @param mixed $value
     * @return mixed
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     * phpcs:disable Syde.Functions.ReturnTypeDeclaration.NoReturnType
     */
    public function decode(mixed $value)
    {
        if (!is_scalar($value)) {
            return $this->column->isNullable() ? null : $this->defaultValue();
        }

        if ($this->core && $this->coreMaybeSerialized) {
            return maybe_unserialize((string) $value);
        }

        if (!$this->core && $this->column->isSerialized()) {
            $value = is_string($value) ? @unserialize($value, ['allowed_classes' => false]) : null;
            if (is_array($value) || $value instanceof \stdClass) {
                return (array) $value;
            }

            return [];
        }

        if ($this->column->isBoolean()) {
            /** @var int|float|string|bool $value */
            return $this->castBoolean($value);
        }

        if ($this->column->isNumeric()) {
            /** @var int|float|string|bool $value */
            return $this->castNumeric($value);
        }

        $type = $this->column->type();

        if ($type === Column::ENUM || $type === Column::SET) {
            return $this->castEnum($value);
        }

        if ($type === Column::JSON) {
            return $this->castJson($value);
        }

        return in_array($type, [Column::TIMESTAMP, Column::DATETIME], true)
            ? $this->castDateTime((string) $value)
            : $this->castStringValue((string) $value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function encode(mixed $value)
    {
        $default = $this->column->isNullable() ? null : $this->defaultValue();
        if ($value === null) {
            return $default;
        }

        if ($this->core) {
            return $this->encodeForCore($value);
        }

        if ($this->column->isSerialized()) {
            return $this->encodeSerialized($value);
        }

        if ($this->column->isBoolean()) {
            /** @var int|float|string|bool $value */
            $cast = $this->castBoolean($value);

            return ($cast === null) ? null : (int) $cast;
        }

        if ($this->column->isNumeric()) {
            /** @var int|float|string|bool $value */
            return $this->castNumeric($value);
        }

        $type = $this->column->type();

        if ($type === Column::ENUM || $type === Column::SET) {
            return $this->castEnum($value);
        }

        if ($type === Column::JSON) {
            return $this->encodeJson($value);
        }

        if ($type === Column::DATETIME || $type === Column::TIMESTAMP) {
            return $this->encodeDatetime($value);
        }

        return $this->encodeStringValue($value);
    }

    /**
     * @param mixed $value
     * @return array<mixed>
     */
    private function castJson(mixed $value): array
    {
        $json = is_string($value) ? json_decode($value, true) : null;
        if (!is_array($json) || (json_last_error() !== JSON_ERROR_NONE)) {
            return [];
        }

        return $json;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function castEnum(mixed $value): ?string
    {
        $choices = $this->column->choices() ?? [''];
        $default = $this->column->isNullable() ? null : (string) reset($choices);

        if (!is_string($value)) {
            return $default;
        }

        foreach ($choices as $choice) {
            if (strtolower($choice) === strtolower($value)) {
                return $choice;
            }
        }

        return $default;
    }

    /**
     * @param mixed $value
     * @return bool|null
     */
    private function castBoolean(mixed $value): ?bool
    {
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            return $this->column->isNullable() ? null : (bool) $this->defaultValue();
        }

        return $filtered;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function castNumeric(mixed $value)
    {
        $isReal = $this->column->isRealNumber();
        if (!is_numeric($value)) {
            return $this->column->isNullable() ? null : $this->defaultValue();
        }
        $value = $isReal ? (float) $value : (int) $value;
        if ($this->column->isBit() || $this->column->isUnsigned()) {
            $value = abs($value);
        }
        return $value;
    }

    /**
     * @param string $value
     * @return \DateTimeInterface|string|null
     *
     * phpcs:disable Syde.CodeQuality.ReturnTypeDeclaration
     */
    private function castDateTime(string $value)
    {
        // phpcs:enable Syde.CodeQuality.ReturnTypeDeclaration
        [$asObject, $targetZone] = $this->column->shouldRetrieveAsDatetime();

        $isTimestamp = $this->column->type() === Column::TIMESTAMP;
        $zone = $isTimestamp ? new \DateTimeZone('UTC') : ($targetZone ?? wp_timezone());

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $zone);
        if (!$date) {
            return $asObject ? null : '0000-00-00 00:00:00';
        }

        if ($isTimestamp && $asObject && $targetZone) {
            $date = $date->setTimezone($targetZone);
        }

        return $asObject ? $date : $date->format('Y-m-d H:i:s');
    }

    /**
     * @param string $value
     * @return string|null
     */
    private function castStringValue(string $value): ?string
    {
        $type = $this->column->type();
        if ($type !== Column::TIME && $type !== Column::YEAR) {
            return $value;
        }

        $default = $this->column->isNullable() ? null : (string) $this->defaultValue();

        if ($type === Column::YEAR) {
            return is_numeric($value) ? sprintf('%d', (int) $value) : $default;
        }

        $regex = '~^([0-9]+)\:([0-9]{1,2})(?:\:([0-9]{1,2})(?:\.([0-9]{1,6}))?)?$~';
        if (!preg_match($regex, $value, $matches)) {
            return $this->column->isNullable() ? null : $value;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (int) ($matches[3] ?? 0);
        $milliseconds = (int) ($matches[4] ?? 0);

        if ($minutes > 59 || $seconds > 59) {
            return $default;
        }

        return sprintf('%d:%02d:%02d.%-06s', $hours, $minutes, $seconds, $milliseconds);
    }

    /**
     * @return mixed
     */
    private function defaultValue()
    {
        if ($this->column->hasDefault()) {
            return $this->column->default();
        }

        $type = $this->column->type();

        if (in_array($type, [Column::TIMESTAMP, Column::DATETIME], true)) {
            return $this->column->shouldRetrieveAsDatetime()[0] ? null : '0000-00-00 00:00:00';
        }

        $type = $this->column->type();

        switch (true) {
            case $this->column->isSerialized():
            case $type === Column::JSON:
                return [];
            case $this->column->isBoolean():
                return false;
            case $this->column->isBit():
            case $this->column->isInteger():
                return 0;
            case $this->column->isRealNumber():
                return 0.0;
            case $type === Column::DATE:
                return '0000-00-00';
            case $type === Column::TIME:
                return '00:00:00';
            case $type === Column::YEAR:
                return '0000';
        }

        return '';
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function encodeForCore(mixed $value): ?string
    {
        if ($this->coreMaybeSerialized) {
            return is_scalar($value) ? (string) $value : (string) maybe_serialize((string) $value);
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-d-m H:i:s');
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $this->column->isNullable() ? null : (string) ($this->defaultValue() ?: '');
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function encodeSerialized(mixed $value): ?string
    {
        if (is_string($value) && is_serialized($value)) {
            return $value;
        }

        if (is_array($value) || $value instanceof \stdClass) {
            return serialize((array) $value);
        }

        return $this->column->isNullable() ? null : (string) $this->defaultValue();
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function encodeJson(mixed $value): ?string
    {
        $default = $this->column->isNullable() ? null : '';

        $flags = JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (is_array($value) || $value instanceof \stdClass) {
            $encoded = json_encode((array) $value, $flags);

            return (json_last_error() === JSON_ERROR_NONE) ? (string) $encoded : $default;
        }

        if (is_string($value)) {
            json_decode($value);

            return (json_last_error() === JSON_ERROR_NONE) ? $value : $default;
        }

        if (is_int($value)) {
            $encoded = json_encode((string) $value, $flags);

            return (json_last_error() === JSON_ERROR_NONE) ? (string) $encoded : $default;
        }

        if (is_float($value)) {
            $encoded = json_encode(rtrim(sprintf('%F', $value), '0'), $flags);

            return (json_last_error() === JSON_ERROR_NONE) ? (string) $encoded : $default;
        }

        return $default;
    }

    /**
     * @param mixed $value
     * @return string|null
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function encodeDatetime(mixed $value): ?string
    {
        $type = $this->column->type();
        $default = $this->column->isNullable() ? null : (string) $this->defaultValue();
        $fullFormat = 'Y-m-d H:i:s';
        $dateOnlyFormat = 'Y-m-d';
        $isDateOnly = $type === Column::DATE;

        if (is_int($value) || is_float($value)) {
            $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $value = $date->setTimestamp((int) $value);
        }

        if ($value instanceof \DateTimeInterface) {
            $value = \DateTimeImmutable::createFromFormat(
                $fullFormat,
                $value->format($fullFormat),
                $value->getTimezone()
            );

            if (!$value) {
                return $default;
            }

            [, $zone] = $this->column->shouldRetrieveAsDatetime();
            if ($zone) {
                $value = $value->setTimezone($zone);
            }

            return $isDateOnly ? $value->format($dateOnlyFormat) : $value->format($fullFormat);
        }

        if (!is_string($value)) {
            return $default;
        }

        $byOnlyDate = \DateTime::createFromFormat($dateOnlyFormat, $value);
        $byFullDate = \DateTime::createFromFormat($fullFormat, $value);

        if (!$byOnlyDate && !$byFullDate) {
            return $default;
        }

        if ($isDateOnly) {
            return $byOnlyDate ? $value : explode(' ', $value, 2)[0];
        }

        return $byOnlyDate ? "{$value} 00:00:00" : $value;
    }

    /**
     * @param mixed $value
     * @return string|null
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function encodeStringValue(mixed $value): ?string
    {
        $type = $this->column->type();
        $default = $this->column->isNullable() ? null : (string) $this->defaultValue();
        $isYear = $type === Column::YEAR;

        if (!$isYear && ($type !== Column::TIME)) {
            return is_string($value) ? $value : $default;
        }

        if (is_int($value) || is_float($value)) {
            if ($isYear && ($value < 999999)) {
                return sprintf('%d', (int) $value);
            }

            $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $value = $date->setTimestamp((int) $value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $isYear ? $value->format('Y') : $value->format('H:i:s');
        }

        if (!is_string($value)) {
            return $default;
        }

        if (!$isYear) {
            return preg_match('~^[0-9]+\:[0-9]{1,2}(?:\:[0-9]{1,2}(?:\.[0-9]{1,6})?)?$~', $value)
                ? $value
                : $default;
        }

        return is_numeric($value) ? sprintf('%d', (int) $value) : $default;
    }
}
