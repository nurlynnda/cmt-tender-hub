<?php

namespace App\Collector;

use Closure;

interface CollectorSource
{
    public function name(): string;

    /**
     * @param  'daily'|'open'  $scope
     * @param  Closure(list<TenderPatch>):void  $onBatch
     * @return int number of tenders seen
     *
     * @throws CollectorException
     */
    public function collect(string $scope, Closure $onBatch): int;
}
