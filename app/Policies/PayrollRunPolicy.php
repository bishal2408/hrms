<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayrollRun;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PayrollRunPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PayrollRun');
    }

    public function view(AuthUser $authUser, PayrollRun $payrollRun): bool
    {
        return $authUser->can('View:PayrollRun');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PayrollRun');
    }

    public function update(AuthUser $authUser, PayrollRun $payrollRun): bool
    {
        return $authUser->can('Update:PayrollRun');
    }

    public function delete(AuthUser $authUser, PayrollRun $payrollRun): bool
    {
        return $authUser->can('Delete:PayrollRun');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PayrollRun');
    }

    public function restore(AuthUser $authUser, PayrollRun $payrollRun): bool
    {
        return $authUser->can('Restore:PayrollRun');
    }

    public function forceDelete(AuthUser $authUser, PayrollRun $payrollRun): bool
    {
        return $authUser->can('ForceDelete:PayrollRun');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PayrollRun');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PayrollRun');
    }

    public function replicate(AuthUser $authUser, PayrollRun $payrollRun): bool
    {
        return $authUser->can('Replicate:PayrollRun');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PayrollRun');
    }
}
