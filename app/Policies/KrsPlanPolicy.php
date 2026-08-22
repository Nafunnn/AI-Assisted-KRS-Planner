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
        if ($user->id === $krsPlan->user_id) {
            return true;
        }

        return $krsPlan->is_shared_with_friends
            && $user->isFriendsWith($krsPlan->user);
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

    public function copyFrom(User $user, KrsPlan $krsPlan): bool
    {
        return $this->view($user, $krsPlan) && $user->id !== $krsPlan->user_id;
    }
}
