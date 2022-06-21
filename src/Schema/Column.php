<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

use Inpsyde\Dbal\Dbal;

final class Column
{
    public const TINYINT = 'TINYINT';
    public const SMALLINT = 'SMALLINT';
    public const MEDIUMINT = 'MEDIUMINT';
    public const INT = 'INT';
    public const BIGINT = 'BIGINT';
    public const DECIMAL = 'DECIMAL';
    public const FLOAT = 'FLOAT';
    public const DOUBLE = 'DOUBLE';
    public const BIT = 'BIT';
    public const DATE = 'DATE';
    public const DATETIME = 'DATETIME';
    public const TIMESTAMP = 'TIMESTAMP';
    public const TIME = 'TIME';
    public const YEAR = 'YEAR';
    public const CHAR = 'CHAR';
    public const VARCHAR = 'VARCHAR';
    public const BINARY = 'BINARY';
    public const VARBINARY = 'VARBINARY';
    public const TINYBLOB = 'TINYBLOB';
    public const BLOB = 'BLOB';
    public const MEDIUMBLOB = 'MEDIUMBLOB';
    public const LONGBLOB = 'LONGBLOB';
    public const TINYTEXT = 'TINYTEXT';
    public const TEXT = 'TEXT';
    public const MEDIUMTEXT = 'MEDIUMTEXT';
    public const LONGTEXT = 'LONGTEXT';
    public const ENUM = 'ENUM';
    public const SET = 'SET';
    public const JSON = 'JSON';

    private const INTEGERS = [
        self::TINYINT,
        self::SMALLINT,
        self::MEDIUMINT,
        self::INT,
        self::BIGINT,
    ];

    private const REAL_NUMBERS = [
        self::FLOAT,
        self::DOUBLE,
        self::DECIMAL,
    ];

    private const DATE_TIMES = [
        self::DATETIME,
        self::TIMESTAMP,
    ];

    private const PRECISION_BASED = [
        self::CHAR,
        self::BIT,
        self::VARCHAR,
        self::BINARY,
        self::VARBINARY,
    ];

    private const SERIALIZABLE = [
        self::CHAR,
        self::VARCHAR,
        self::TINYTEXT,
        self::MEDIUMTEXT,
        self::TEXT,
        self::LONGTEXT,
    ];

    private const STRINGS = [
        self::CHAR,
        self::VARCHAR,
        self::TINYTEXT,
        self::TEXT,
        self::MEDIUMTEXT,
        self::LONGTEXT,
        self::ENUM,
        self::SET,
    ];

    private const ATTR_DEFAULT = 'DEFAULT';
    private const ATTR_DISPLAY_SIZE = 'DISPLAY_SIZE';
    private const ATTR_UNSIGNED = 'UNSIGNED';
    private const ATTR_NOT_NULL = 'NOT_NULL';
    private const ATTR_AUTO_INCREMENT = 'AUTO_INCREMENT';
    private const ATTR_ZERO_FILL = 'ZERO_FILL';
    private const ATTR_PRECISION = 'PRECISION';
    private const ATTR_SCALE = 'SCALE';
    private const ATTR_DEFAULT_CREATE_TO_NOW = 'DEFAULT_CREATE_TO_NOW';
    private const ATTR_DEFAULT_UPDATE_TO_NOW = 'DEFAULT_UPDATE_TO_NOW';
    private const ATTR_RETRIEVE_AS_DATETIME = 'RETRIEVE_AS_DATETIME';
    private const ATTR_RETRIEVE_AS_DATETIME_ZONE = 'RETRIEVE_AS_DATETIME_ZONE';
    private const ATTR_SERIALIZED = 'SERIALIZED';
    private const ATTR_CHOICES = 'ENUM_CHOICES';
    private const ATTR_CHARSET = 'CHARSET';
    private const ATTR_COLLATE = 'COLLATE';
    private const ATTR_IS_BOOL = 'IS_BOOL';

    private const DEF_SIZE_REGXP = '~^\((?:\s?([0-9]+)(?:\s?,\s?([0-9]+)\s?)?)\)~';
    private const DEF_CHOICES_REGXP = '~^\(([^\)]+)\)~';
    private const DEF_DEFAULT_REGXP = '~(?: |^)default (\'|")?(.+?)(?:\'|")?(?: |$)~';
    private const DEF_NOT_NULL_REGXP = '~(?: |^)not null(?: |$)~';
    private const DEF_UNSIGNED_REGXP = '~(?: |^)unsigned(?: |$)~';
    private const DEF_AUTO_INCREMENT_REGXP = '~(?: |^)auto_increment(?: |$)~';

    /**
     * @var array<string,string>|null
     */
    private static $characterSets = null;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $format;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @param string $name
     * @param int|null $default
     * @return Column
     */
    public static function tinyInt(string $name, ?int $default = null): Column
    {
        return new static($name, self::TINYINT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param int|null $default
     * @return Column
     */
    public static function smallInt(string $name, ?int $default = null): Column
    {
        return new static($name, self::SMALLINT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param int|null $default
     * @return Column
     */
    public static function mediumInt(string $name, ?int $default = null): Column
    {
        return new static($name, self::MEDIUMINT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param int|null $default
     * @return Column
     */
    public static function int(string $name, ?int $default = null): Column
    {
        return new static($name, self::INT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param int|null $default
     * @return Column
     */
    public static function bigInt(string $name, ?int $default = null): Column
    {
        return new static($name, self::BIGINT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param int $size
     * @param int|null $default
     * @return Column
     */
    public static function bit(string $name, int $size = 1, ?int $default = null): Column
    {
        if ($size < 0 || $size > 63) {
            throw new \Exception('BIT columns size can be between 0 and 63.');
        }

        if ($default !== null && strlen(decbin($default)) > $size) {
            throw new \Exception("Default value for BIT column can't exceed its size.");
        }

        return new static(
            $name,
            self::BIT,
            [self::ATTR_PRECISION => $size, self::ATTR_DEFAULT => $default]
        );
    }

    /**
     * @param string $name
     * @param int $size
     * @param bool|null $default
     * @return Column
     */
    public static function bool(string $name, ?bool $default = null): Column
    {
        $column = static::bit($name, 1);
        $column->attributes[self::ATTR_IS_BOOL] = true;
        if ($default !== null) {
            $column->attributes[self::ATTR_DEFAULT] = $default;
        }

        return $column;
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function entityId(string $name): Column
    {
        return static::bigInt($name)->makeNotNull()->makeUnsigned()->makeAutoIncrement();
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function foreignId(string $name): Column
    {
        return static::bigInt($name)->makeNotNull()->makeUnsigned();
    }

    /**
     * @param string $name
     * @param int $precision
     * @param int $scale
     * @param float|null $default
     * @return Column
     */
    public static function decimal(
        string $name,
        int $precision = 10,
        int $scale = 0,
        ?float $default = null
    ): Column {

        if ($precision < 0 || $precision > 65 || $scale < 0 || $scale > 64) {
            throw new \Exception(
                "DECIMAL columns precision can be between 0 and 65 and a scale between 0 and 64."
            );
        }

        return new static(
            $name,
            self::DECIMAL,
            [
                self::ATTR_PRECISION => $precision,
                self::ATTR_SCALE => $scale,
                self::ATTR_DEFAULT => $default,
            ]
        );
    }

    /**
     * @param string $name
     * @param int|null $precision
     * @param int|null $scale
     * @param float|null $default
     * @return Column
     */
    public static function float(
        string $name,
        ?int $precision = null,
        ?int $scale = null,
        ?float $default = null
    ): Column {

        if ($precision !== null) {
            if ($precision < 0 || $precision > 53) {
                throw new \Exception('DECIMAL columns precision can be between 0 and 53.');
            }

            if ($precision > 23) {
                return static::double($name, $precision, $scale, $default);
            }
        }

        if ($scale !== null) {
            if ($precision === null) {
                throw new \Exception('DECIMAL columns can only present scale with precision.');
            }

            if ($scale < 0 || $scale > 52) {
                throw new \Exception('DECIMAL columns scale between 0 and 52.');
            }
        }

        return new static(
            $name,
            self::FLOAT,
            [
                self::ATTR_PRECISION => $precision,
                self::ATTR_SCALE => $scale,
                self::ATTR_DEFAULT => $default,
            ]
        );
    }

    /**
     * @param string $name
     * @param int|null $precision
     * @param int|null $scale
     * @param float|null $default
     * @return Column
     */
    public static function double(
        string $name,
        ?int $precision = null,
        ?int $scale = null,
        ?float $default = null
    ): Column {

        if ($precision !== null) {
            if ($precision < 0 || $precision > 53) {
                throw new \Exception('DOUBLE columns precision can be between 0 and 53.');
            }

            if ($precision < 24) {
                return static::float($name, $precision, $scale, $default);
            }
        }

        if ($scale !== null) {
            if ($precision === null) {
                throw new \Exception('DOUBLE columns can only present scale with precision.');
            }

            if ($scale < 0 || $scale > 52) {
                throw new \Exception('DOUBLE columns scale between 0 and 52.');
            }
        }

        return new static(
            $name,
            self::DOUBLE,
            [
                self::ATTR_PRECISION => $precision,
                self::ATTR_SCALE => $scale,
                self::ATTR_DEFAULT => $default,
            ]
        );
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function datetime(string $name, ?string $default = null): Column
    {
        $col = new static($name, self::DATETIME, []);
        if ($default !== null) {
            $col->attributes[self::ATTR_DEFAULT] = $col->ensureDateTimeFitsFormat($default);
        }

        return $col;
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function timestamp(string $name, ?string $default = null): Column
    {
        $col = new static($name, self::TIMESTAMP, []);
        if ($default !== null) {
            $col->attributes[self::ATTR_DEFAULT] = $col->ensureDateTimeFitsFormat($default);
        }

        return $col;
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function date(string $name, ?string $default = null): Column
    {
        if ($default !== null) {
            $date = \DateTime::createFromFormat('Y-m-d', $default);
            if (!$date) {
                throw new \Exception("'{$default}' has no the correct format for a DATE column.");
            }
        }

        return new static($name, self::DATE, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function time(string $name, ?string $default = null): Column
    {
        if (
            $default !== null
            && !preg_match('~^-?[0-9]{2,3}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{6})?$~', $default)
        ) {
            throw new \Exception("'{$default}' has no the correct format for a TIME column.");
        }

        return new static($name, self::TIME, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function year(string $name, ?string $default = null): Column
    {
        if ($default !== null) {
            if (!is_numeric($default)) {
                throw new \Exception("'{$default}' is not a valid default for a YEAR column.");
            }

            $default = (string)((int)$default);
        }

        return new static($name, self::YEAR, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param int $size
     * @param string|null $default
     * @return Column
     */
    public static function char(string $name, int $size, ?string $default = null): Column
    {
        if ($size < 0 || $size > 255) {
            throw new \Exception("CHAR column size must be between 0 and 255, {$size} provided.");
        }

        if ($default !== null && strlen($default) > $size) {
            throw new \Exception(
                "Default value for a CHAR column, can't be greater than column size."
            );
        }

        return new static(
            $name,
            self::CHAR,
            [self::ATTR_PRECISION => $size, self::ATTR_DEFAULT => $default]
        );
    }

    /**
     * @param string $name
     * @param int $size
     * @param string|null $default
     * @return Column
     */
    public static function varChar(string $name, int $size, ?string $default = null): Column
    {
        if ($size < 0 || $size > 65535) {
            throw new \Exception(
                "VARCHAR column size must be between 0 and 65.535, {$size} provided."
            );
        }

        if ($default !== null && strlen($default) > $size) {
            throw new \Exception(
                "Default value for a VARCHAR column, can't be greater than column size."
            );
        }

        return new static(
            $name,
            self::VARCHAR,
            [self::ATTR_PRECISION => $size, self::ATTR_DEFAULT => $default]
        );
    }

    /**
     * @param string $name
     * @param int $size
     * @param string|null $default
     * @return Column
     */
    public static function binary(string $name, int $size, ?string $default = null): Column
    {
        if ($size < 0 || $size > 255) {
            throw new \Exception("BINARY column size must be between 0 and 255, {$size} provided.");
        }

        if ($default !== null && strlen($default) > $size) {
            throw new \Exception(
                "Default value for a BINARY column, can't be greater than column size."
            );
        }

        return new static(
            $name,
            self::BINARY,
            [self::ATTR_PRECISION => $size, self::ATTR_DEFAULT => $default]
        );
    }

    /**
     * @param string $name
     * @param int $size
     * @param string|null $default
     * @return Column
     */
    public static function varBinary(string $name, int $size, ?string $default = null): Column
    {
        if ($size < 0 || $size > 65535) {
            throw new \Exception(
                "VARBINARY column size must be between 0 and 65.535, {$size} provided."
            );
        }

        if ($default !== null && strlen($default) > $size) {
            throw new \Exception(
                "Default value for a VARBINARY column, can't be greater than column size."
            );
        }

        return new static(
            $name,
            self::VARBINARY,
            [self::ATTR_PRECISION => $size, self::ATTR_DEFAULT => $default]
        );
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function tinyBlob(string $name): Column
    {
        return new static($name, self::TINYBLOB, []);
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function blob(string $name): Column
    {
        return new static($name, self::BLOB, []);
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function mediumBlob(string $name): Column
    {
        return new static($name, self::MEDIUMBLOB, []);
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function longBlob(string $name): Column
    {
        return new static($name, self::LONGBLOB, []);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function tinyText(string $name, ?string $default = null): Column
    {
        return new static($name, self::TINYTEXT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function text(string $name, ?string $default = null): Column
    {
        return new static($name, self::TEXT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function mediumText(string $name, ?string $default = null): Column
    {
        return new static($name, self::MEDIUMTEXT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return Column
     */
    public static function longText(string $name, ?string $default = null): Column
    {
        return new static($name, self::LONGTEXT, [self::ATTR_DEFAULT => $default]);
    }

    /**
     * @param string $name
     * @param string $first
     * @param string ...$choices
     * @return Column
     */
    public static function enum(string $name, string $first, string ...$choices): Column
    {
        array_unshift($choices, $first);

        $choices = array_values(array_filter(array_unique($choices)));

        if (count($choices) > 63) {
            /**
             * Theoretic limit is 65535, but for practical reasons and limit imposed by .frm file
             * we decided to have the same limitation of SET columns.
             * @see https://dev.mysql.com/doc/refman/5.7/en/create-table-files.html#limits-frm-file
             */

            throw new \Exception("ENUM columns can have a maximum of 63 members.");
        }

        return new static($name, self::ENUM, [self::ATTR_CHOICES => $choices]);
    }

    /**
     * @param string $name
     * @param string $first
     * @param string ...$choices
     * @return Column
     */
    public static function set(string $name, string $first, string ...$choices): Column
    {
        array_unshift($choices, $first);

        $choices = array_values(array_filter(array_unique($choices)));

        if (count($choices) > 63) {
            throw new \Exception("SET columns can have a maximum of 63 members.");
        }

        return new static($name, self::SET, [self::ATTR_CHOICES => $choices]);
    }

    /**
     * @param string $name
     * @return Column
     */
    public static function json(string $name): Column
    {
        return new static($name, self::JSON, []);
    }

    /**
     * @param string $specs
     * @return Column|null
     */
    public static function parseRawDefinition(string $definition): ?Column
    {
        [$name, $type, $specs] = static::normalizeRawDefinition($definition);
        if (!$name || !$type || !$specs) {
            return null;
        }

        $realNumber = in_array($type, self::REAL_NUMBERS, true);
        $attributes = [];
        $attributes[self::ATTR_NOT_NULL] = (bool)preg_match(self::DEF_NOT_NULL_REGXP, $specs);

        if (preg_match(self::DEF_DEFAULT_REGXP, $specs, $matches)) {
            $default = $matches[1] ? $matches[2] : (int)$matches[2];
            if ($default === '"' || $default === "'") {
                $default = '';
            }

            $attributes[self::ATTR_DEFAULT] = $realNumber ? (float)$default : $default;
        }

        if ($realNumber || in_array($type, self::INTEGERS, true)) {
            $unsigned = (bool)preg_match(self::DEF_UNSIGNED_REGXP, $specs);
            $autoIncr = (bool)preg_match(self::DEF_AUTO_INCREMENT_REGXP, $specs);
            $attributes[self::ATTR_UNSIGNED] = $unsigned;
            $attributes[self::ATTR_AUTO_INCREMENT] = $autoIncr;
        }

        if (
            ($realNumber || in_array($type, self::PRECISION_BASED, true))
            && preg_match(self::DEF_SIZE_REGXP, $specs, $matches)
        ) {
            $precision = $matches[1] ?? null;
            $scale = $realNumber ? ($matches[2] ?? null) : null;
            ($precision !== null) and $attributes[self::ATTR_PRECISION] = (int)$precision;
            ($scale !== null) and $attributes[self::ATTR_SCALE] = (int)$scale;
        }

        if (in_array($type, [self::ENUM, self::SET], true)) {
            preg_match(self::DEF_CHOICES_REGXP, $specs, $matches);
            if (empty($matches[1])) {
                return null;
            }

            $attributes[self::ATTR_CHOICES] = [];
            $rawChoices = explode(',', $matches[1]);
            foreach ($rawChoices as $rawChoice) {
                $attributes[self::ATTR_CHOICES][] = trim($rawChoice, ' "\'');
            }
        }

        return new static($name, $type, $attributes);
    }

    /**
     * @param string $definition
     * @return array{string|null, string|null, string|null}
     */
    private static function normalizeRawDefinition(string $definition): array
    {
        if (!$definition) {
            return [null, null, null];
        }

        $definition = preg_replace('/\s+/', ' ', rtrim(trim($definition), ','));
        $parts = explode(' ', (string)$definition, 3);
        $name = empty($parts[0]) ? null : $parts[0];
        $type = empty($parts[1]) ? null : strtoupper(trim($parts[1]));
        $specs = empty($parts[2]) ? null : strtolower(trim($parts[2]));
        if (!$name || !$type || !$specs) {
            return [null, null, null];
        }

        if (preg_match("~^([A-Z]+)\(([^\)]+)\)~", $type, $matches)) {
            $type = $matches[1];
            $specs = '(' . trim($matches[2]) . ") {$specs}";
        }

        // phpcs:disable WordPressVIPMinimum.Constants.ConstantString
        if (!defined(__CLASS__ . "::{$type}")) {
            // phpcs:enable WordPressVIPMinimum.Constants.ConstantString
            return [null, null, null];
        }

        return [$name, $type, $specs];
    }

    /**
     * @param string $name
     * @param string $type
     * @param array $attributes
     */
    private function __construct(string $name, string $type, array $attributes)
    {
        if (!SchemasRegister::validateColumnName($name)) {
            throw new \Exception("'{$name}' is not a valid column name.");
        }

        $this->name = $name;
        $this->type = $type;
        $this->format = ($this->isBit() || in_array($type, self::INTEGERS, true)) ? '%d' : '%s';
        $this->attributes = $attributes;
    }

    /**
     * @return Column
     */
    public function makeUnsigned(): Column
    {
        if (
            !in_array($this->type, self::INTEGERS, true)
            && !in_array($this->type, self::REAL_NUMBERS, true)
        ) {
            throw new \Exception(
                'Only integer and floating-point columns can have "UNSIGNED" attribute.'
            );
        }

        $default = $this->default();
        if ($default !== null && $default < 0) {
            throw new \Exception('Can\'t make unsigned a column with a negative default.');
        }

        $this->attributes[self::ATTR_UNSIGNED] = true;

        return $this;
    }

    /**
     * @param int $displaySize
     * @return Column
     */
    public function makeZeroFill(int $displaySize): Column
    {
        if (!in_array($this->type, self::INTEGERS, true)) {
            throw new \Exception(
                'Only integer columns can have "ZEROFILL" attribute.'
            );
        }

        $this->attributes[self::ATTR_ZERO_FILL] = true;
        $this->attributes[self::ATTR_DISPLAY_SIZE] = $displaySize;
        $this->makeUnsigned();

        return $this;
    }

    /**
     * @return Column
     */
    public function makeAutoIncrement(): Column
    {
        if (
            !in_array($this->type, self::INTEGERS, true)
            && !in_array($this->type, self::REAL_NUMBERS, true)
        ) {
            throw new \Exception(
                'Only numeric and floating-point columns can have "AUTO_INCREMENT" attribute.'
            );
        }

        if (!empty($this->attributes[self::ATTR_DEFAULT])) {
            throw new \Exception(
                "Columns with default can't also have \"AUTO_INCREMENT\" attribute."
            );
        }

        $this->makeNotNull();
        $this->attributes[self::ATTR_AUTO_INCREMENT] = true;

        return $this;
    }

    /**
     * @param bool $onCreate
     * @param bool $onUpdate
     * @return Column
     */
    public function useCurrentTimestampAsDefault(
        bool $onCreate = true,
        bool $onUpdate = true
    ): Column {

        if (!in_array($this->type, self::DATE_TIMES, true)) {
            throw new \Exception(
                'Only DATETIME AND TIMESTAMP columns can use current timestamp as default.'
            );
        }

        if ($onCreate && ($this->attributes[self::ATTR_DEFAULT] ?? null) !== null) {
            throw new \Exception(
                "Can\'t default to current timestamp on creation because a default is already set."
            );
        }

        $onCreate and $this->attributes[self::ATTR_DEFAULT_CREATE_TO_NOW] = true;
        $onUpdate and $this->attributes[self::ATTR_DEFAULT_UPDATE_TO_NOW] = true;

        return $this;
    }

    /**
     * @param \DateTimeZone|null $zone
     * @return Column
     */
    public function retrieveAsDateTime(?\DateTimeZone $zone = null): Column
    {
        if (!in_array($this->type, self::DATE_TIMES, true)) {
            throw new \Exception(
                'Only DATETIME AND TIMESTAMP columns can be retrieved as DateTime objects.'
            );
        }

        $zone and $this->useDefaultTimeZone($zone);
        $this->attributes[self::ATTR_RETRIEVE_AS_DATETIME] = true;

        return $this;
    }

    /**
     * @param \DateTimeZone $zone
     * @return Column
     */
    public function useDefaultTimeZone(\DateTimeZone $zone): Column
    {
        if (!in_array($this->type, self::DATE_TIMES, true)) {
            throw new \Exception('Only DATETIME AND TIMESTAMP columns can use a default timezone.');
        }

        if (
            ($this->attributes[self::ATTR_RETRIEVE_AS_DATETIME_ZONE] ?? null)
            && ($this->attributes[self::ATTR_RETRIEVE_AS_DATETIME] ?? null)
        ) {
            throw new \Exception('Timezone for the column was set via "retrieveAsDateTime".');
        }

        $this->attributes[self::ATTR_RETRIEVE_AS_DATETIME_ZONE] = $zone;

        return $this;
    }

    /**
     * @return Column
     */
    public function makeNotNull(): Column
    {
        $this->attributes[self::ATTR_NOT_NULL] = true;

        return $this;
    }

    /**
     * @return Column
     */
    public function storeSerialized(): Column
    {
        if (!in_array($this->type, self::SERIALIZABLE, true)) {
            throw new \Exception(
                'Only CHAR/VARCHAR and (TINY/MEDIUM/SMALL/LONG)TEXT can store serialized values.'
            );
        }

        if (
            ($this->type === self::CHAR || $this->type === self::VARCHAR)
            && ((int)($this->attributes[self::ATTR_PRECISION] ?? 0) < 255)
        ) {
            throw new \Exception(
                "{$this->type} column size must be 255 bytes or more to store serialized values."
            );
        }

        if (!empty($this->attributes[self::ATTR_DEFAULT])) {
            throw new \Exception('Serialized columns can\'t have non-empty default.');
        }

        $this->attributes[self::ATTR_SERIALIZED] = true;

        return $this;
    }

    /**
     * @param string|null $charset
     * @param string|null $collation
     * @return Column
     */
    public function useCharsetCollation(?string $charset = null, ?string $collation = null): Column
    {
        if (!in_array($this->type, self::STRINGS, true)) {
            throw new \Exception("{$this->type} columns do not support CHARSET/COLLATE attribute.");
        }

        if ($collation) {
            $collationParts = explode('_', (string)$collation, 2);
            if (empty($collationParts[1])) {
                $collation = null;
            }
            if ($charset === null && !empty($collationParts[0])) {
                $charset = $collationParts[0];
            }
        }

        if (!$charset) {
            return $this;
        }

        $charset = strtolower($charset);
        $collation = $collation === null ? null : strtolower($collation);

        $supported = $this->allCharacterSetsAndCollation();
        if ($supported) {
            if (!array_key_exists($charset, $supported)) {
                return $this;
            }
            if ($collation === null || strpos($collation, $charset) !== 0) {
                $collation = $supported[$charset];
            }
        }

        $this->attributes[self::ATTR_CHARSET] = $charset;
        $this->attributes[self::ATTR_COLLATE] = $collation;

        return $this;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function format(): string
    {
        return $this->format;
    }

    /**
     * @return array<int, string>|null
     */
    public function choices(): ?array
    {
        if ($this->type !== self::ENUM && $this->type !== self::SET) {
            return null;
        }

        /** @var array<int, string> $choices */
        $choices = $this->attributes[self::ATTR_CHOICES] ?? [];

        return $choices;
    }

    /**
     * @return string
     */
    public function schemaDefinition(): string
    {
        $name = $this->name();
        $specs = $this->integerDefinition()
            ?? $this->precisionBasedDefinition()
            ?? $this->realDefinition()
            ?? $this->dateTimeDefinition()
            ?? $this->enumOrSetDefinition()
            ?? $this->baseDefinition();

        return "`{$name}` {$specs}";
    }

    /**
     * @return bool
     */
    public function isAutoIncrement(): bool
    {
        return !empty($this->attributes[self::ATTR_AUTO_INCREMENT]);
    }

    /**
     * @return bool
     */
    public function isInteger(): bool
    {
        return in_array($this->type, self::INTEGERS, true);
    }

    /**
     * @return bool
     */
    public function isRealNumber(): bool
    {
        return in_array($this->type, self::REAL_NUMBERS, true);
    }

    /**
     * @return bool
     */
    public function isBit(): bool
    {
        return $this->type === self::BIT;
    }

    /**
     * @return bool
     */
    public function isNumeric(): bool
    {
        return $this->isInteger()
            || $this->isRealNumber()
            || ($this->isBit() && !$this->isBoolean());
    }

    /**
     * @return bool
     */
    public function isDateOrTimeInfo(): bool
    {
        return in_array(
            $this->type(),
            [Column::TIMESTAMP, Column::DATETIME, Column::DATE, Column::YEAR, Column::TIME],
            true
        );
    }

    /**
     * @return bool
     */
    public function isBoolean(): bool
    {
        return $this->isBit() && ($this->attributes[self::ATTR_IS_BOOL] ?? false);
    }

    /**
     * @return bool
     */
    public function isUnsigned(): bool
    {
        return ($this->isInteger() || $this->isRealNumber())
            && !empty($this->attributes[self::ATTR_UNSIGNED]);
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return empty($this->attributes[self::ATTR_NOT_NULL]);
    }

    /**
     * @return bool
     */
    public function isSerialized(): bool
    {
        return !empty($this->attributes[self::ATTR_SERIALIZED]);
    }

    /**
     * @return array{bool, \DateTimeZone|null}
     */
    public function shouldRetrieveAsDatetime(): array
    {
        $should = in_array($this->type, self::DATE_TIMES, true)
            && !empty($this->attributes[self::ATTR_RETRIEVE_AS_DATETIME]);

        if (!$should) {
            return [false, null];
        }

        /** @var \DateTimeZone|null $zone */
        $zone = $this->attributes[self::ATTR_RETRIEVE_AS_DATETIME_ZONE] ?? null;

        return [true, $zone];
    }

    /**
     * @return bool
     */
    public function hasDefault(): bool
    {
        return ($this->attributes[self::ATTR_DEFAULT] ?? null) !== null;
    }

    /**
     * @return mixed
     */
    public function default()
    {
        return $this->attributes[self::ATTR_DEFAULT] ?? null;
    }

    /**
     * @return bool
     */
    public function isRequiredOnInsert(): bool
    {
        if (
            in_array($this->type, self::DATE_TIMES, true)
            && !empty($this->attributes[self::ATTR_DEFAULT_CREATE_TO_NOW])
        ) {
            return false;
        }

        return !$this->isNullable()
            && !$this->hasDefault()
            && !$this->isAutoIncrement()
            && ($this->type !== self::ENUM)
            && ($this->type !== self::SET);
    }

    /**
     * @return string|null
     */
    private function integerDefinition(): ?string
    {
        if (!in_array($this->type, self::INTEGERS, true)) {
            return null;
        }

        $size = $this->attributes[self::ATTR_DISPLAY_SIZE] ?? null;
        $unsigned = $this->attributes[self::ATTR_UNSIGNED] ?? null;
        $zerofill = $this->attributes[self::ATTR_ZERO_FILL] ?? false;
        $autoIncrement = $this->attributes[self::ATTR_AUTO_INCREMENT] ?? false;

        $def = $this->type;
        $size and $def .= "({$size})";
        $unsigned and $def .= ' UNSIGNED';
        $zerofill and $def .= ' ZEROFILL';
        $autoIncrement and $def .= " AUTO_INCREMENT";

        return $def . $this->notNullDefinition() . $this->defaultDefinition();
    }

    /**
     * @return string|null
     */
    private function realDefinition(): ?string
    {
        if (!in_array($this->type, self::REAL_NUMBERS, true)) {
            return null;
        }

        /** @var int|null $precision */
        $precision = $this->attributes[self::ATTR_PRECISION] ?? null;
        /** @var int|null $scale */
        $scale = $this->attributes[self::ATTR_SCALE] ?? null;
        $unsigned = $this->attributes[self::ATTR_UNSIGNED] ?? null;

        $def = $this->type;
        if ($precision !== null) {
            $attr = sprintf('%d', $precision);
            ($scale !== null) and $attr .= sprintf(',%d', $scale);
            $def .= "({$attr})";
        }
        $unsigned and $def .= ' UNSIGNED';

        return $def . $this->notNullDefinition() . $this->defaultDefinition();
    }

    /**
     * @return string|null
     */
    private function precisionBasedDefinition(): ?string
    {
        if (!in_array($this->type, self::PRECISION_BASED, true)) {
            return null;
        }

        $precision = $this->attributes[self::ATTR_PRECISION] ?? ($this->isBit() ? 1 : 16);

        $def = "{$this->type}({$precision})";

        return $def . $this->notNullDefinition() . $this->defaultDefinition();
    }

    /**
     * @return string|null
     */
    private function dateTimeDefinition(): ?string
    {
        if (!in_array($this->type, self::DATE_TIMES, true)) {
            return null;
        }

        $defaultOnCreate = $this->attributes[self::ATTR_DEFAULT_CREATE_TO_NOW] ?? false;
        $defaultOnUpdate = $this->attributes[self::ATTR_DEFAULT_UPDATE_TO_NOW] ?? false;

        $def = $this->type . $this->notNullDefinition();
        $def .= $defaultOnCreate ? ' DEFAULT CURRENT_TIMESTAMP' : $this->defaultDefinition();
        $defaultOnUpdate and $def .= ' ON UPDATE CURRENT_TIMESTAMP';

        return $def;
    }

    /**
     * @return string|null
     */
    private function enumOrSetDefinition(): ?string
    {
        if (!in_array($this->type, [self::ENUM, self::SET], true)) {
            return null;
        }

        $notNull = $this->attributes[self::ATTR_NOT_NULL] ?? false;
        $choices = (array)($this->attributes[self::ATTR_CHOICES] ?? ['']);

        $def = "{$this->type}('" . implode("','", $choices) . "')";
        if ($notNull) {
            $default = reset($choices);
            $def .= " NOT NULL DEFAULT '{$default}'";
        }

        return $def . $this->charsetCollateDefinition();
    }

    /**
     * @return string
     */
    private function baseDefinition(): string
    {
        return $this->type
            . $this->notNullDefinition()
            . $this->defaultDefinition()
            . $this->charsetCollateDefinition();
    }

    /**
     * @return string
     */
    private function notNullDefinition(): string
    {
        if ($this->attributes[self::ATTR_NOT_NULL] ?? false) {
            return ' NOT NULL';
        }

        return '';
    }

    /**
     * @return string
     */
    private function defaultDefinition(): string
    {
        /** @var int|float|string|null $default */
        $default = $this->attributes[self::ATTR_DEFAULT] ?? null;
        if (($this->type === self::JSON) || ($default === null) || $this->isSerialized()) {
            return '';
        }

        $format = ($this->isBit() || in_array($this->type, self::INTEGERS, true)) ? '%d' : '%s';

        $wpdb = Dbal::wpdb();

        return (string)$wpdb->prepare(" DEFAULT {$format}", $default);
    }

    /**
     * @return string
     */
    private function charsetCollateDefinition(): string
    {
        $charset = $this->attributes[self::ATTR_CHARSET] ?? null;
        $collation = $this->attributes[self::ATTR_COLLATE] ?? null;

        if ($charset !== null || $collation !== null) {
            $def = '';
            ($charset !== null) and $def .= " CHARACTER SET {$charset}";
            ($collation !== null) and $def .= " COLLATE {$collation}";

            return $def;
        }

        return '';
    }

    /**
     * @param string $datetime
     * @param string $type
     * @return string
     */
    private function ensureDateTimeFitsFormat(string $datetime): string
    {
        $date = \DateTime::createFromFormat($datetime, 'Y-m-d H:i:s')
            ?: \DateTime::createFromFormat($datetime, 'Y-m-d');

        if (!$date) {
            throw new \Exception("Invalid default '{$datetime}' for {$this->type} column.");
        }

        return $datetime;
    }

    /**
     * @return array<string, string>
     */
    private function allCharacterSetsAndCollation(): array
    {
        if (is_array(static::$characterSets)) {
            return static::$characterSets;
        }

        $raw = Dbal::wpdb()->get_results("SHOW CHARACTER SET", ARRAY_A);

        /** @var array<string, string>|null $characterSets */
        $characterSets = ($raw && is_array($raw))
            ? array_column($raw, 'Default collation', 'Charset')
            : null;

        static::$characterSets = $characterSets
            ? array_change_key_case($characterSets, CASE_LOWER)
            : [];

        return static::$characterSets;
    }
}
