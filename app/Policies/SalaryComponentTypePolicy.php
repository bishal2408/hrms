<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalaryComponentType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SalaryComponentTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SalaryComponentType');
    }

    public function view(AuthUser $authUser, SalaryComponentType $salaryComponentType): bool
    {
        return $authUser->can('View:SalaryComponentType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SalaryComponentType');
    }

    public function update(AuthUser $authUser, SalaryComponentType $salaryComponentType): bool
    {
        return $authUser->can('Update:SalaryComponentType');
    }

    public function delete(AuthUser $authUser, SalaryComponentType $salaryComponentType): bool
    {
        return $authUser->can('Delete:SalaryComponentType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SalaryComponentType');
    }

    public function restore(AuthUser $authUser, SalaryComponentType $salaryComponentType): bool
    {
        return $authUser->can('Restore:SalaryComponentType');
    }

    public function forceDelete(AuthUser $authUser, SalaryComponentType $salaryComponentType): bool
    {
        return $authUser->can('ForceDelete:SalaryComponentType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SalaryComponentType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SalaryComponentType');
    }

    public function replicate(AuthUser $authUser, SalaryComponentType $salaryComponentType): bool
    {
        return $authUser->can('Replicate:SalaryComponentType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SalaryComponentType');
    }
}
