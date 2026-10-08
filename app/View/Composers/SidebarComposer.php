<?php

namespace App\View\Composers;

use App\Enums\TenderStatus;
use App\Models\Tender;
use Illuminate\View\View;

class SidebarComposer
{
    public function compose(View $view): void
    {
        $counts = Tender::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $view->with('counts', collect(TenderStatus::cases())
            ->mapWithKeys(fn (TenderStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]));
    }
}
