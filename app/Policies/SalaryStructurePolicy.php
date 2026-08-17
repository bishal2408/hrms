<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalaryStructure;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SalaryStructurePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SalaryStructure');
    }

    public function view(AuthUser $authUser, SalaryStructure $salaryStructure): bool
    {
        return $authUser->can('View:SalaryStructure');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SalaryStructure');
    }

    public function update(AuthUser $authUser, SalaryStructure $salaryStructure): bool
    {
        return $authUser->can('Update:SalaryStructure');
    }

    public function delete(AuthUser $authUser, SalaryStructure $salaryStructure): bool
    {
        return $authUser->can('Delete:SalaryStructure');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SalaryStructure');
    }

    public function restore(AuthUser $authUser, SalaryStructure $salaryStructure): bool
    {
        return $authUser->can('Restore:SalaryStructure');
    }

    public function forceDelete(AuthUser $authUser, SalaryStructure $salaryStructure): bool
    {
        return $authUser->can('ForceDelete:SalaryStructure');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SalaryStructure');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SalaryStructure');
    }

    public function replicate(AuthUser $authUser, SalaryStructure $salaryStructure): bool
    {
        return $authUser->can('Replicate:SalaryStructure');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SalaryStructure');
    }
}
