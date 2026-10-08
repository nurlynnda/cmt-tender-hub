<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Pdf\QuotationPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class QuotationPdfController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation, QuotationPdf $pdf)
    {
        Gate::authorize('view', $quotation);
        try {
            $bytes = $pdf->bytes($quotation);
        } catch (Throwable $e) {
            report($e);

            return response()->view('quotations.pdf-failed', ['quotation' => $quotation], 500);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$quotation->number.'.pdf"',
        ]);
    }
}
