<?php

namespace App\Filament\Employee\Resources\LeaveRequests\Pages;

use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveRequestService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveRequests extends ManageRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ->using() routes creation through LeaveRequestService instead of
            // a bare LeaveRequest::create() — that's what applies the balance
            // check, the overlap check, and sets status explicitly rather than
            // relying on the DB default (see the service for why).
            // ->visible() is explicit rather than relying on the modal
            // CreateAction picking up Resource::canCreate() implicitly — an
            // account with no linked employee has nothing to submit against.
            CreateAction::make()
                ->visible(fn (): bool => LeaveRequestResource::canCreate())
                ->using(function (array $data, Action $action): LeaveRequest {
                    try {
                        return app(LeaveRequestService::class)->submit(
                            employee: auth()->user()->employee,
                            leaveType: LeaveType::findOrFail($data['leave_type_id']),
                            startDate: $data['start_date'],
                            endDate: $data['end_date'],
                            reason: $data['reason'] ?? null,
                        );
                    } catch (Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
