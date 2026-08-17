<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Designation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DesignationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Designation');
    }

    public function view(AuthUser $authUser, Designation $designation): bool
    {
        return $authUser->can('View:Designation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Designation');
    }

    public function update(AuthUser $authUser, Designation $designation): bool
    {
        return $authUser->can('Update:Designation');
    }

    public function delete(AuthUser $authUser, Designation $designation): bool
    {
        return $authUser->can('Delete:Designation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Designation');
    }

    public function restore(AuthUser $authUser, Designation $designation): bool
    {
        return $authUser->can('Restore:Designation');
    }

    public function forceDelete(AuthUser $authUser, Designation $designation): bool
    {
        return $authUser->can('ForceDelete:Designation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Designation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Designation');
    }

    public function replicate(AuthUser $authUser, Designation $designation): bool
    {
        return $authUser->can('Replicate:Designation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Designation');
    }
}
