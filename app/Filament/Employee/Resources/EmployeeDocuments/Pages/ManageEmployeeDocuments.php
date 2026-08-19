<?php

namespace App\Filament\Employee\Resources\EmployeeDocuments\Pages;

use App\Filament\Employee\Resources\EmployeeDocuments\EmployeeDocumentResource;
use Filament\Resources\Pages\ManageRecords;

/** No header actions: a document is never created here, only viewed. */
class ManageEmployeeDocuments extends ManageRecords
{
    protected static string $resource = EmployeeDocumentResource::class;
}
