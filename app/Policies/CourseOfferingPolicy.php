<?php

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\User;

class CourseOfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CourseOffering $courseOffering): bool
    {
        return $user->id === $courseOffering->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CourseOffering $courseOffering): bool
    {
        return $user->id === $courseOffering->user_id;
    }

    public function delete(User $user, CourseOffering $courseOffering): bool
    {
        return $user->id === $courseOffering->user_id;
    }
}
