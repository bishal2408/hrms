<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payslip;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PayslipPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Payslip');
    }

    public function view(AuthUser $authUser, Payslip $payslip): bool
    {
        return $authUser->can('View:Payslip');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Payslip');
    }

    public function update(AuthUser $authUser, Payslip $payslip): bool
    {
        return $authUser->can('Update:Payslip');
    }

    public function delete(AuthUser $authUser, Payslip $payslip): bool
    {
        return $authUser->can('Delete:Payslip');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Payslip');
    }

    public function restore(AuthUser $authUser, Payslip $payslip): bool
    {
        return $authUser->can('Restore:Payslip');
    }

    public function forceDelete(AuthUser $authUser, Payslip $payslip): bool
    {
        return $authUser->can('ForceDelete:Payslip');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Payslip');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Payslip');
    }

    public function replicate(AuthUser $authUser, Payslip $payslip): bool
    {
        return $authUser->can('Replicate:Payslip');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Payslip');
    }
}
