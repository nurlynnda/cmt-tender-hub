<?php

namespace App\Collector;

final class SourceName
{
    private const LABELS = ['myprocurement' => 'MyProcurement', 'span' => 'SPAN', 'llm' => 'LLM', 'kwsp' => 'KWSP'];

    public static function label(string $source): string
    {
        return self::LABELS[$source] ?? ucfirst($source);
    }

    /** @return array<string,string> every source a user can filter by (KWSP is history only but filterable) */
    public static function all(): array
    {
        return self::LABELS;
    }
}
