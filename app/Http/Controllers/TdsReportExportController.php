<?php

namespace App\Http\Controllers;

use App\Exports\TdsReportExport;
use App\Services\PayrollComplianceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams the TDS report as an .xlsx. Same reasoning as
 * VatRegisterExportController/PfSsfRemittanceExportController.
 */
class TdsReportExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizeDownload();

        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : null;
        $until = $request->filled('until') ? Carbon::parse($request->string('until')) : null;

        $report = app(PayrollComplianceReportService::class)->tds($from, $until);

        return Excel::download(new TdsReportExport($report), 'tds-report.xlsx');
    }

    /** Same role check as TdsReportPage::canAccess(). */
    private function authorizeDownload(): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);
        abort_unless($user->hasAnyRole(['super_admin', 'payroll_accountant']), 403);
    }
}
