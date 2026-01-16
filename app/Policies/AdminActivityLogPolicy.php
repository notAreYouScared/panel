<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdminActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view adminActivityLog');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('view adminActivityLog');
    }

    public function create(User $user): bool
    {
        return false; // Admin activity logs are created programmatically only
    }

    public function update(User $user, Model $model): bool
    {
        return false; // Admin activity logs are immutable
    }

    public function delete(User $user, Model $model): bool
    {
        return false; // Admin activity logs cannot be deleted
    }

    public function deleteAny(User $user): bool
    {
        return false; // Admin activity logs cannot be deleted
    }
}
