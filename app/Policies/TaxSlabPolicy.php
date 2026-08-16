<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxSlab;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TaxSlabPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TaxSlab');
    }

    public function view(AuthUser $authUser, TaxSlab $taxSlab): bool
    {
        return $authUser->can('View:TaxSlab');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TaxSlab');
    }

    public function update(AuthUser $authUser, TaxSlab $taxSlab): bool
    {
        return $authUser->can('Update:TaxSlab');
    }

    public function delete(AuthUser $authUser, TaxSlab $taxSlab): bool
    {
        return $authUser->can('Delete:TaxSlab');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TaxSlab');
    }

    public function restore(AuthUser $authUser, TaxSlab $taxSlab): bool
    {
        return $authUser->can('Restore:TaxSlab');
    }

    public function forceDelete(AuthUser $authUser, TaxSlab $taxSlab): bool
    {
        return $authUser->can('ForceDelete:TaxSlab');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TaxSlab');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TaxSlab');
    }

    public function replicate(AuthUser $authUser, TaxSlab $taxSlab): bool
    {
        return $authUser->can('Replicate:TaxSlab');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TaxSlab');
    }
}
