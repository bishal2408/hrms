<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayrollRate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PayrollRatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PayrollRate');
    }

    public function view(AuthUser $authUser, PayrollRate $payrollRate): bool
    {
        return $authUser->can('View:PayrollRate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PayrollRate');
    }

    public function update(AuthUser $authUser, PayrollRate $payrollRate): bool
    {
        return $authUser->can('Update:PayrollRate');
    }

    public function delete(AuthUser $authUser, PayrollRate $payrollRate): bool
    {
        return $authUser->can('Delete:PayrollRate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PayrollRate');
    }

    public function restore(AuthUser $authUser, PayrollRate $payrollRate): bool
    {
        return $authUser->can('Restore:PayrollRate');
    }

    public function forceDelete(AuthUser $authUser, PayrollRate $payrollRate): bool
    {
        return $authUser->can('ForceDelete:PayrollRate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PayrollRate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PayrollRate');
    }

    public function replicate(AuthUser $authUser, PayrollRate $payrollRate): bool
    {
        return $authUser->can('Replicate:PayrollRate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PayrollRate');
    }
}
