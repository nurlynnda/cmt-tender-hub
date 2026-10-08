<?php

namespace App\Collector;

interface Fetcher
{
    /** @throws CollectorException */
    public function getJson(string $url): array;

    /** @throws CollectorException */
    public function getText(string $url): string;
}
