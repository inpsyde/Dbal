<?php

declare(strict_types=1);

namespace Syde\Dbal\Schema;

final class Version
{
    private ?string $version;

    /**
     * @return Version
     */
    public static function newEmpty(): Version
    {
        return new static(null);
    }

    /**
     * @param string $version
     * @return Version
     */
    public static function new(string $version): Version
    {
        $version = strtolower(str_replace(' ', '', $version));
        if (preg_match('~^[0-9][a-z0-9-\.]?~', $version) !== 1) {
            return static::newEmpty();
        }

        $main = strtok($version, '-');
        $suffix = strtok('-');

        $versionString = '';
        $token = strtok($main, '.');
        while ($token !== false) {
            if (is_numeric($token)) {
                $tokenStr = (string) abs((int) $token);
                $versionString .= ($versionString === '') ? $tokenStr : ".{$tokenStr}";
            }
            $token = strtok('.');
        }

        if ($versionString === '') {
            static::newEmpty();
        }

        if ($suffix !== false) {
            $versionString .= "-{$suffix}";
        }

        return new static($versionString);
    }

    /**
     * @param string|null $version
     */
    private function __construct(?string $version)
    {
        $this->version = $version;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->version !== null;
    }

    /**
     * @param Version $version
     * @return bool
     */
    public function match(Version $version): bool
    {
        return $this->version === $version->version;
    }

    /**
     * @param Version $version
     * @return bool
     */
    public function equals(Version $version): bool
    {
        return $this->compare($version, 'eq');
    }

    /**
     * @param Version $version
     * @return bool
     */
    public function greaterThan(Version $version): bool
    {
        return $this->compare($version, '>');
    }

    /**
     * @param Version $version
     * @return bool
     */
    public function greaterThanOrEquals(Version $version): bool
    {
        return $this->compare($version, '>=');
    }

    /**
     * @param Version $version
     * @return bool
     */
    public function lowerThan(Version $version): bool
    {
        return $this->compare($version, '<');
    }

    /**
     * @param Version $version
     * @return bool
     */
    public function lowerThanOrEquals(Version $version): bool
    {
        return $this->compare($version, '<=');
    }

    /**
     * @return string|null
     */
    public function value(): ?string
    {
        return $this->version;
    }

    /**
     * @return lowercase-string
     */
    public function __toString(): string
    {
        return ($this->version === null) ? '' : $this->version;
    }

    /**
     * @param Version $version
     * @param ">"|">="|"<"|"<="|"eq" $operator
     * @return bool
     */
    private function compare(Version $version, string $operator): bool
    {
        return $this->isValid()
            && $version->isValid()
            && version_compare($this->version, $version->version, $operator);
    }
}
