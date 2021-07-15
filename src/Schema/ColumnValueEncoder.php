<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

/**
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
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

    /**
     * @var Column
     */
    private $column;

    /**
     * @var bool
     */
    private $coreMaybeSerialized = false;

    /**
     * @var bool
     */
    private $core = false;

    /**
     * @param Column $column
     * @return ColumnValueEncoder
     */
    public static function forCore(Column $column, WpSchema $schema): ColumnValueEncoder
    {
        $instance = new self($column);
        $instance->core = true;
        if (!empty(self::CORE_MAYBE_SERIALIZED[$schema->name()][$column->name()])) {
            $instance->coreMaybeSerialized = true;
        }

        return $instance;
    }

    /**
     * @param Column $column
     * @return ColumnValueEncoder
     */
    public static function for(Column $column): ColumnValueEncoder
    {
        return new self($column);
    }

    /**
     * @param Column $column
     */
    private function __construct(Column $column)
    {
        $this->column = $column;
    }

    /**
     * @param $value
     * @return mixed|null
     *
     * @psalm-suppress MissingReturnType
     * @psalm-suppress MissingParamType
     */
    public function decode($value)
    {
        if ($value === null || !is_scalar($value)) {
            return $this->column->isNullable() ? null : $this->defaultValue();
        }

        if ($this->core && $this->coreMaybeSerialized) {
            return maybe_unserialize((string)$value);
        }

        if (!$this->core && $this->column->isSerialized()) {
            $value = is_string($value) ? @unserialize($value, ['allowed_classes' => false]) : null;
            if (is_array($value) || $value instanceof \stdClass) {
                return (array)$value;
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
            ? $this->castDateTime((string)$value)
            : $this->castStringValue((string)$value);
    }

    /**
     * @param $value
     * @return mixed|null
     *
     * @psalm-suppress MissingReturnType
     * @psalm-suppress MissingParamType
     */
    public function encode($value)
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

            return $cast === null ? null : (int)$cast;
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
     * @return array
     *
     * @psalm-suppress MissingParamType
     */
    private function castJson($value): array
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
     *
     * @psalm-suppress MissingParamType
     */
    private function castEnum($value): ?string
    {
        $choices = $this->column->choices() ?? [''];
        $default = $this->column->isNullable()
            ? null
            : (string)reset($choices);

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
     * @param int|float|string|bool $value
     * @return bool|null
     */
    private function castBoolean($value): ?bool
    {
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            return $this->column->isNullable() ? null : (bool)$this->defaultValue();
        }

        return (bool)$filtered;
    }

    /**
     * @param int|float|string|bool $value
     * @return mixed|null
     *
     * @psalm-suppress MissingReturnType
     * @psalm-suppress MissingParamType
     */
    private function castNumeric($value)
    {
        $isReal = $this->column->isRealNumber();
        if (!is_numeric($value)) {
            return $this->column->isNullable() ? null : $this->defaultValue();
        }
        $value = $isReal ? (float)$value : (int)$value;
        if ($this->column->isBit() || $this->column->isUnsigned()) {
            $value = abs($value);
        }
        return $value;
    }

    /**
     * @param string $value
     * @return \DateTimeInterface|string|null
     *
     * @psalm-suppress MissingReturnType
     */
    private function castDateTime(string $value)
    {
        [$asObject, $targetZone] = $this->column->shouldRetrieveAsDatetime();

        $isTimestamp = $this->column->type() === Column::TIMESTAMP;

        /** @var \DateTimeZone $zone */
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

        $default = $this->column->isNullable() ? null : (string)$this->defaultValue();

        if ($type === Column::YEAR) {
            return is_numeric($value) ? sprintf('%d', (int)$value) : $default;
        }

        $regex = '~^([0-9]+)\:([0-9]{1,2})(?:\:([0-9]{1,2})(?:\.([0-9]{1,6}))?)?$~';
        if (!preg_match($regex, $value, $matches)) {
            return $this->column->isNullable() ? null : $value;
        }

        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        $seconds = (int)($matches[3] ?? 0);
        $milliseconds = (int)($matches[4] ?? 0);

        if ($minutes > 59 || $seconds > 59) {
            return $default;
        }

        return sprintf('%d:%02d:%02d.%-06s', $hours, $minutes, $seconds, $milliseconds);
    }

    /**
     * @return mixed|null
     *
     * @psalm-suppress MissingReturnType
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    private function defaultValue()
    {
        // phpcs:enable Generic.Metrics.CyclomaticComplexity
        if ($this->column->hasDefault()) {
            return $this->column->default();
        }

        $type = $this->column->type();

        if (in_array($type, [Column::TIMESTAMP, Column::DATETIME], true)) {
            return $this->column->retrieveAsDateTime() ? null : '0000-00-00 00:00:00';
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
     *
     * @psalm-suppress MissingParamType
     */
    private function encodeForCore($value): ?string
    {
        if ($this->coreMaybeSerialized) {
            return is_scalar($value) ? (string)$value : (string)maybe_serialize((string)$value);
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-d-m H:i:s');
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return $this->column->isNullable() ? null : (string)($this->defaultValue() ?: '');
    }

    /**
     * @param mixed $value
     * @return string|null
     *
     * @psalm-suppress MissingParamType
     */
    private function encodeSerialized($value): ?string
    {
        if (is_string($value) && is_serialized($value)) {
            return $value;
        }

        if (is_array($value) || $value instanceof \stdClass) {
            return serialize((array)$value);
        }

        return $this->column->isNullable() ? null : (string)$this->defaultValue();
    }

    /**
     * @param $value
     * @return string|null
     *
     * @psalm-suppress MissingParamType
     */
    private function encodeJson($value): ?string
    {
        $default = $this->column->isNullable() ? null : '';

        $flags = JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (is_array($value) || $value instanceof \stdClass) {
            $encoded = json_encode((array)$value, $flags);

            return json_last_error() === JSON_ERROR_NONE ? (string)$encoded : $default;
        }

        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $value;
            }

            $encoded = json_encode($value, $flags);

            return json_last_error() === JSON_ERROR_NONE ? (string)$encoded : $default;
        }

        if (is_int($value)) {
            $encoded = json_encode((string)$value, $flags);

            return json_last_error() === JSON_ERROR_NONE ? (string)$encoded : $default;
        }

        if (is_float($value)) {
            $encoded = json_encode(rtrim(sprintf('%F', $value), '0'), $flags);

            return json_last_error() === JSON_ERROR_NONE ? (string)$encoded : $default;
        }

        return $default;
    }

    /**
     * @param $value
     * @return string|null
     *
     * @psalm-suppress MissingParamType
     */
    private function encodeDatetime($value): ?string
    {
        $type = $this->column->type();
        $default = $this->column->isNullable() ? null : (string)$this->defaultValue();
        $fullFormat = 'Y-m-d H:i:s';
        $dateOnlyFormat = 'Y-m-d';
        $isDateOnly = $type === Column::DATE;

        if (is_int($value) || is_float($value)) {
            $value = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $value = $value->setTimestamp((int)$value);
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

            /** @var \DateTimeZone|null $zone */
            [, $zone] = $this->column->shouldRetrieveAsDatetime();
            $zone and $value = $value->setTimezone($zone);

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
     * @param $value
     * @return string|null
     *
     * @psalm-suppress MissingParamType
     */
    private function encodeStringValue($value): ?string
    {
        $type = $this->column->type();
        $default = $this->column->isNullable() ? null : (string)$this->defaultValue();
        $isYear = $type === Column::YEAR;

        if (!$isYear && $type !== Column::TIME) {
            return is_string($value) ? $value : $default;
        }

        if (is_int($value) || is_float($value)) {
            if ($isYear && $value < 999999) {
                return sprintf('%d', (int)$value);
            }

            $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $value = $date->setTimestamp((int)$value);
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

        return is_numeric($value) ? sprintf('%d', (int)$value) : $default;
    }
}
