<?php

namespace App\Policies;

use App\Models\AdminActivityLog;
use App\Models\User;

class AdminActivityLogPolicy
{
    protected string $modelName = 'adminActivityLog';

    public function viewAny(User $user): bool
    {
        return $user->can('view ' . $this->modelName);
    }

    public function view(User $user, AdminActivityLog $log): bool
    {
        return $user->can('view ' . $this->modelName);
    }

    /**
     * Admin activity logs cannot be created through the UI.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Admin activity logs cannot be updated.
     */
    public function update(User $user, AdminActivityLog $log): bool
    {
        return false;
    }

    /**
     * Admin activity logs cannot be deleted.
     */
    public function delete(User $user, AdminActivityLog $log): bool
    {
        return false;
    }

    /**
     * Admin activity logs cannot be bulk deleted.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Admin activity logs cannot be replicated.
     */
    public function replicate(User $user, AdminActivityLog $log): bool
    {
        return false;
    }
}
