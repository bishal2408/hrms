<?php

namespace App\Filament\Employee\Resources\Payslips\Pages;

use App\Filament\Employee\Resources\Payslips\PayslipResource;
use Filament\Resources\Pages\ManageRecords;

/** No header actions: a payslip is never created here, only viewed. */
class ManagePayslips extends ManageRecords
{
    protected static string $resource = PayslipResource::class;
}
