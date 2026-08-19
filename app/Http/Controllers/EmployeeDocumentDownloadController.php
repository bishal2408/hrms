<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a stored employee document. Deliberately not a Filament action —
 * same reasoning as PayslipPdfController: Livewire actions are AJAX calls
 * and can't hand back a binary download, so both the admin relation manager
 * and the employee self-service resource link here as a real URL instead.
 */
class EmployeeDocumentDownloadController extends Controller
{
    public function download(EmployeeDocument $employeeDocument): StreamedResponse
    {
        $this->authorizeDownload($employeeDocument);

        return Storage::disk('local')->download($employeeDocument->path, $employeeDocument->original_filename);
    }

    /**
     * Documents are more sensitive than the employee record itself
     * (citizenship copies, contracts) — HR/payroll may download any
     * employee's documents, and an employee may download their own. Unlike
     * Employee::scopeVisibleTo(), a manager's view of their direct reports
     * does NOT extend to documents (decision: 2026-08-19).
     *
     * Checked by role, not by the `View:EmployeeDocument` permission string:
     * the 'employee' role also holds that permission (so its own scoped
     * self-service resource can open at all), and a raw `can()` check here
     * would then let any employee pass regardless of whose document it is.
     * `Employee::ROLES_WITH_FULL_ACCESS` is the exact group the decision
     * above names, so it's reused rather than duplicated.
     */
    private function authorizeDownload(EmployeeDocument $employeeDocument): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        if ($user->hasAnyRole(Employee::ROLES_WITH_FULL_ACCESS)) {
            return;
        }

        $employeeDocument->loadMissing('employee');

        abort_unless($employeeDocument->employee->user_id === $user->id, 403);
    }
}
