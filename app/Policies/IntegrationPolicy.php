<?php

namespace App\Policies;

use App\Models\Tenant\Integration;
use App\Models\Tenant\User;

class IntegrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Integration $integration): bool
    {
        return $user->id === $integration->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Integration $integration): bool
    {
        return $user->id === $integration->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Integration $integration): bool
    {
        return $user->id === $integration->user_id;
    }

    /**
     * Custom: trigger sync
     */
    public function sync(User $user, Integration $integration): bool
    {
        return $user->id === $integration->user_id;
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Integration $integration): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Integration $integration): bool
    // {
    //     return false;
    // }
}
