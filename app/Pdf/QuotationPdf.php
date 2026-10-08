<?php

namespace App\Pdf;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** Builds the quotation PDF. One Blade layout is used for the Preview tab and the download. */
class QuotationPdf
{
    public function html(Quotation $quotation): string
    {
        $quotation->loadMissing('items', 'preparer');

        return view('quotations.pdf', [
            'q' => $quotation,
            'totals' => $quotation->totals(),
            'stamp' => $this->stamp($quotation),
            'terms' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $quotation->terms)))),
        ])->render();
    }

    public function bytes(Quotation $quotation): string
    {
        return Pdf::loadHTML($this->html($quotation))->setPaper('a4')->output();
    }

    /** The stamp as a data: URI, or null when switched off, not uploaded or missing. */
    private function stamp(Quotation $q): ?string
    {
        $path = $q->letterhead['stamp_path'] ?? null;
        $disk = Storage::disk('local');
        if (! $q->show_stamp || ! $path || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }
}
