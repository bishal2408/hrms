<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Streams a payslip as a PDF. Deliberately not a Filament action: Livewire
 * actions are AJAX calls and can't hand the browser a binary download, so
 * the admin and employee panels both link here as a real URL instead
 * (Action::make(...)->url(...)) rather than trying to return a response from
 * a Livewire action closure.
 */
class PayslipPdfController extends Controller
{
    public function download(Payslip $payslip): Response
    {
        $this->authorizeDownload($payslip);

        $payslip->loadMissing(['employee.department', 'employee.designation', 'payrollRun', 'adjustments']);

        $pdf = Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'company' => Company::current(),
        ]);

        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $payslip->employee->employee_code,
            $payslip->payrollRun->period_start->format('Y-m'),
        );

        return $pdf->download($filename);
    }

    /**
     * Staff with payroll visibility may download any payslip. An employee
     * may download their own, but only once the run is finalized — a draft
     * figure can still change and must never look official.
     */
    private function authorizeDownload(Payslip $payslip): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        if ($user->can('View:PayrollRun')) {
            return;
        }

        $isOwnFinalizedPayslip = $payslip->employee->user_id === $user->id
            && $payslip->payrollRun->isFinalized();

        abort_unless($isOwnFinalizedPayslip, 403);
    }
}
