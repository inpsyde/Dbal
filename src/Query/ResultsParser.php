<?php

declare(strict_types=1);

namespace Syde\Dbal\Query;

use Syde\Dbal\Schema\Schemas;

class ResultsParser
{
    /** @var list<ColumnsResultParser> */
    private array $parsers;

    /**
     * @param Schemas $tables
     * @param Aliases|null $aliases
     * @return ResultsParser
     */
    public static function forTables(Schemas $tables, ?Aliases $aliases = null): ResultsParser
    {
        if (!$aliases) {
            $aliases = Aliases::new();
        }

        $parsers = [];
        foreach ($tables as $table) {
            $parsers[] = ColumnsResultParser::new($table->columns(), $table->name(), $aliases);
        }

        return new self(...$parsers);
    }

    /**
     * @param ColumnsResultParser ...$parsers
     *
     * @no-named-arguments
     */
    private function __construct(ColumnsResultParser ...$parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function parse(array $data): array
    {
        foreach ($this->parsers as $resultParser) {
            $data = $resultParser->parse($data);
        }

        return $data;
    }
}
