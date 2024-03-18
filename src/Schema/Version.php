<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Schema;

/**
 * @template T of non-empty-lowercase-string|null
 * @psalm-pure
 */
final class Version
{
    /** @var T */
    private ?string $version;

    /**
     * @return Version<null>
     */
    public static function newEmpty(): Version
    {
        return new static(null);
    }

    /**
     * @param string $version
     * @return Version<non-empty-lowercase-string>|Version<null>
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
            is_numeric($token) and $versionString .= (string) abs((int) $token);
            $token = strtok('.');
        }

        if ($versionString === '') {
            static::newEmpty();
        }

        ($suffix !== false) and $versionString .= "-{$suffix}";
        /** @var non-empty-lowercase-string $versionString */
        return new static($versionString);
    }

    /**
     * @param T $version
     */
    private function __construct(?string $version)
    {
        $this->version = $version;
    }

    /**
     * @return bool
     *
     * @psalm-assert-if-true non-empty-lowercase-string $this->version
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $this
     * @psalm-assert-if-false null $this->version
     * @psalm-assert-if-false Version<null> $this
     */
    public function isValid(): bool
    {
        return $this->version !== null;
    }

    /**
     * @param Version<T> $version
     * @return bool
     */
    public function match(Version $version): bool
    {
        return $this->version === $version->version;
    }

    /**
     * @param Version<T> $version
     * @return bool
     *
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $this
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $version
     */
    public function equals(Version $version): bool
    {
        return $this->compare($version, 'eq');
    }

    /**
     * @param Version<T> $version
     * @return bool
     *
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $this
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $version
     */
    public function higherThan(Version $version): bool
    {
        return $this->compare($version, '>');
    }

    /**
     * @param Version<T> $version
     * @return bool
     *
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $this
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $version
     */
    public function higherThanOrEquals(Version $version): bool
    {
        return $this->compare($version, '>=');
    }

    /**
     * @param Version<T> $version
     * @return bool
     *
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $this
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $version
     */
    public function lowerThan(Version $version): bool
    {
        return $this->compare($version, '<');
    }

    /**
     * @param Version<T> $version
     * @return bool
     *
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $this
     * @psalm-assert-if-true Version<non-empty-lowercase-string> $version
     */
    public function lowerThanOrEquals(Version $version): bool
    {
        return $this->compare($version, '<=');
    }

    /**
     * @return T
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
     * @param Version<T> $version
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
