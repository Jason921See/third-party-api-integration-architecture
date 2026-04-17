<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\IntegrationInsight;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\Response;

class IntegrationInsightPolicy
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
    public function view(User $user, IntegrationInsight $integrationInsight): bool
    {
        return $integrationInsight->integration->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IntegrationInsight $integrationInsight): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IntegrationInsight $integrationInsight): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IntegrationInsight $integrationInsight): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IntegrationInsight $integrationInsight): bool
    {
        return false;
    }
}
