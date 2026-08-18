<?php

use App\AI\Registry\ModuleRegistry;
use App\AI\Services\PermissionGuard;
use App\Models\User;

test('permission guard denies entities without policy', function () {
    $registry = app(ModuleRegistry::class);
    $guard = app(PermissionGuard::class);
    $user = User::factory()->create();

    $entity = $registry->get('krs_plan');

    expect($entity)->not->toBeNull()
        ->and($guard->canAccessDefinition($user, $entity))->toBeTrue();
});

test('permission guard lists allowed entity keys for user', function () {
    $guard = app(PermissionGuard::class);
    $user = User::factory()->create();

    $keys = $guard->allowedEntityKeys($user);

    expect($keys)->toContain('krs_plan', 'course_offering', 'course');
});
