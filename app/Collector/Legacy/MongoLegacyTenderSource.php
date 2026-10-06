<?php

namespace App\Collector\Legacy;

use MongoDB\Client;

/** Reads tms-v2's MongoDB `tenders` collection. Only used by the one-time import. */
final class MongoLegacyTenderSource implements LegacyTenderSource
{
    private \MongoDB\Collection $collection;

    public function __construct(string $uri, string $database)
    {
        $this->collection = (new Client($uri, [], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]))
            ->selectCollection($database, 'tenders');
    }

    public function count(): int
    {
        return $this->collection->countDocuments();
    }

    public function documents(): iterable
    {
        yield from $this->collection->find([], ['batchSize' => 1000]);
    }
}
