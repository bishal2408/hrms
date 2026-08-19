<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeDocument;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmployeeDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeDocument');
    }

    public function view(AuthUser $authUser, EmployeeDocument $employeeDocument): bool
    {
        return $authUser->can('View:EmployeeDocument');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeDocument');
    }

    public function update(AuthUser $authUser, EmployeeDocument $employeeDocument): bool
    {
        return $authUser->can('Update:EmployeeDocument');
    }

    public function delete(AuthUser $authUser, EmployeeDocument $employeeDocument): bool
    {
        return $authUser->can('Delete:EmployeeDocument');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmployeeDocument');
    }

    public function restore(AuthUser $authUser, EmployeeDocument $employeeDocument): bool
    {
        return $authUser->can('Restore:EmployeeDocument');
    }

    public function forceDelete(AuthUser $authUser, EmployeeDocument $employeeDocument): bool
    {
        return $authUser->can('ForceDelete:EmployeeDocument');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EmployeeDocument');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EmployeeDocument');
    }

    public function replicate(AuthUser $authUser, EmployeeDocument $employeeDocument): bool
    {
        return $authUser->can('Replicate:EmployeeDocument');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EmployeeDocument');
    }
}
