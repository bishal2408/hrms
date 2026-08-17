<?php

use App\Http\Controllers\PayslipPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// A real route, not a Filament action: Livewire actions are AJAX calls and
// can't hand the browser a binary PDF download. Shared by both panels — the
// controller does its own authorization since a request here could come from
// either. No 'auth' middleware: Laravel's default Authenticate middleware
// redirects an unauthenticated request to a route named 'login', which
// doesn't exist globally in a Filament-only app (auth lives per-panel, e.g.
// admin/login, employee/login) — it has no way to know which panel a bare
// PDF link belongs to. The controller checks auth()->user() itself and
// responds 403, which is unambiguous regardless of which panel would have
// applied.
Route::get('/payroll/payslips/{payslip}/pdf', [PayslipPdfController::class, 'download'])
    ->name('payslips.pdf');
