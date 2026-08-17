<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * No CreateAction: HR and managers decide requests here, they don't file
 * requests on an employee's behalf (that stays self-service only, matching
 * the roadmap's "leave request + manager approval workflow" scope for 2b).
 */
class ManageLeaveRequests extends ManageRecords
{
    protected static string $resource = LeaveRequestResource::class;
}
