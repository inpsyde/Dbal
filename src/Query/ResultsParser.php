<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

use Inpsyde\Dbal\Schema\Schemas;

class ResultsParser
{
    /**
     * @var ColumnsResultParser[]
     */
    private $parsers;

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
     */
    private function __construct(ColumnsResultParser ...$parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * @param array $data
     * @return array
     */
    public function parse(array $data): array
    {
        foreach ($this->parsers as $resultParser) {
            $data = $resultParser->parse($data);
        }

        return $data;
    }
}
