<?php

namespace App\Http\Controllers;

use App\Exports\VatRegisterExport;
use App\Services\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams the VAT register as an .xlsx. Same reasoning as
 * PayslipPdfController/InvoicePdfController: a real route, not a Filament
 * action, since Livewire actions can't hand back a binary download. `from`/
 * `until` arrive as AD date strings — VatRegisterPage has already converted
 * them from BS before building the link, so no BS parsing happens here.
 */
class VatRegisterExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizeDownload();

        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : null;
        $until = $request->filled('until') ? Carbon::parse($request->string('until')) : null;

        $report = app(AccountingReportService::class)->vatRegister($from, $until);

        return Excel::download(new VatRegisterExport($report), 'vat-register.xlsx');
    }

    /** Same role check as VatRegisterPage::canAccess() — Accounting is payroll_accountant's domain throughout this app. */
    private function authorizeDownload(): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);
        abort_unless($user->hasAnyRole(['super_admin', 'payroll_accountant']), 403);
    }
}
