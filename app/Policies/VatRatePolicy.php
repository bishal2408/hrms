<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VatRate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VatRatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VatRate');
    }

    public function view(AuthUser $authUser, VatRate $vatRate): bool
    {
        return $authUser->can('View:VatRate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VatRate');
    }

    public function update(AuthUser $authUser, VatRate $vatRate): bool
    {
        return $authUser->can('Update:VatRate');
    }

    public function delete(AuthUser $authUser, VatRate $vatRate): bool
    {
        return $authUser->can('Delete:VatRate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VatRate');
    }

    public function restore(AuthUser $authUser, VatRate $vatRate): bool
    {
        return $authUser->can('Restore:VatRate');
    }

    public function forceDelete(AuthUser $authUser, VatRate $vatRate): bool
    {
        return $authUser->can('ForceDelete:VatRate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VatRate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VatRate');
    }

    public function replicate(AuthUser $authUser, VatRate $vatRate): bool
    {
        return $authUser->can('Replicate:VatRate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VatRate');
    }
}
