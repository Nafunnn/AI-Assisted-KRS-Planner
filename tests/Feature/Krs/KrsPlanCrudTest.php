<?php

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can create multiple plans for the same offering', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana KRS']);

    $this->actingAs($user)
        ->post(route('krs.plans.store', $offering), [
            'name' => 'Alternatif pagi',
        ])
        ->assertRedirect();

    expect($offering->krsPlans()->count())->toBe(2)
        ->and($offering->krsPlans()->latest('id')->first()?->name)->toBe('Alternatif pagi');
});

test('plans on the same offering keep independent selected sections', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    $planA = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana A']);
    $planB = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana B']);
    $course = Course::factory()->for($offering)->create();
    $section = CourseSection::factory()->for($course)->create();

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $planA), [
            'course_section_id' => $section->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    expect($planA->fresh()->items)->toHaveCount(1)
        ->and($planB->fresh()->items)->toHaveCount(0);
});

test('user cannot delete the last remaining plan', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $this->actingAs($user)
        ->from(route('krs.planner', [$offering, $plan]))
        ->delete(route('krs.plans.destroy', $plan))
        ->assertRedirect();

    $this->assertModelExists($plan);
});

test('user can delete a plan when another remains', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    $planA = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana A']);
    $planB = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana B']);

    $this->actingAs($user)
        ->delete(route('krs.plans.destroy', $planB))
        ->assertRedirect(route('krs.planner', [$offering, $planA]));

    $this->assertModelMissing($planB);
    $this->assertModelExists($planA);
});

test('user cannot create a plan on another users offering', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $offering = CourseOffering::factory()->for($owner)->create();

    $this->actingAs($other)
        ->post(route('krs.plans.store', $offering))
        ->assertForbidden();
});

test('planner latest redirects to the newest plan', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Lama']);
    $newest = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Baru']);

    $this->actingAs($user)
        ->get(route('krs.planner.latest', $offering))
        ->assertRedirect(route('krs.planner', [$offering, $newest]));
});

test('planner returns not found when plan does not belong to offering', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    $otherOffering = CourseOffering::factory()->for($user)->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $this->actingAs($user)
        ->get(route('krs.planner', [$otherOffering, $plan]))
        ->assertNotFound();
});

test('user can rename a plan', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana KRS']);

    $this->actingAs($user)
        ->patch(route('krs.plans.update', $plan), [
            'name' => 'Kelas pagi',
        ])
        ->assertRedirect(route('krs.planner', [$offering, $plan]));

    expect($plan->fresh()->name)->toBe('Kelas pagi');
});

test('krs index lists all plans for an offering', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->for($user)->create();
    KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana KRS']);
    KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Alternatif']);

    $this->actingAs($user)
        ->get(route('krs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('krs/Index')
            ->has('offerings.0.plans', 2)
        );
});
