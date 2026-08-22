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
        return $user->isAdmin() || $courseOffering->isPublished();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin();
    }

    public function sync(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin();
    }
}
