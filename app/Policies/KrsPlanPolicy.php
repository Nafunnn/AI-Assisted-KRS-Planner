<?php

namespace App\Policies;

use App\Models\KrsPlan;
use App\Models\User;

class KrsPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KrsPlan $krsPlan): bool
    {
        return $user->id === $krsPlan->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, KrsPlan $krsPlan): bool
    {
        return $user->id === $krsPlan->user_id;
    }

    public function delete(User $user, KrsPlan $krsPlan): bool
    {
        return $user->id === $krsPlan->user_id;
    }
}
