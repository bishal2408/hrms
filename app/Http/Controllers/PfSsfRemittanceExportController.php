<?php

namespace App\Http\Controllers;

use App\Exports\PfSsfRemittanceExport;
use App\Services\PayrollComplianceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams the PF/SSF remittance report as an .xlsx. Same reasoning as
 * VatRegisterExportController: a real route, not a Filament action, since
 * Livewire actions can't hand back a binary download. `from`/`until` arrive
 * as AD date strings — PfSsfRemittancePage has already converted them from
 * BS before building the link, so no BS parsing happens here.
 */
class PfSsfRemittanceExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizeDownload();

        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : null;
        $until = $request->filled('until') ? Carbon::parse($request->string('until')) : null;

        $report = app(PayrollComplianceReportService::class)->pfSsfRemittance($from, $until);

        return Excel::download(new PfSsfRemittanceExport($report), 'pf-ssf-remittance.xlsx');
    }

    /** Same role check as PfSsfRemittancePage::canAccess(). */
    private function authorizeDownload(): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);
        abort_unless($user->hasAnyRole(['super_admin', 'payroll_accountant']), 403);
    }
}
