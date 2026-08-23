<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Streams an invoice as a PDF. Same reasoning as PayslipPdfController: a
 * real route, not a Filament action, since Livewire actions can't hand back
 * a binary download.
 */
class InvoicePdfController extends Controller
{
    public function download(Invoice $invoice): Response
    {
        $this->authorizeDownload();

        $invoice->loadMissing(['customer', 'lineItems']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => Company::current(),
        ]);

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }

    private function authorizeDownload(): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);
        abort_unless($user->can('View:Invoice'), 403);
    }
}
