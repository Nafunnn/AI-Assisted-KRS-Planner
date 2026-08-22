<?php

use App\Enums\FriendshipStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\Friendship;
use App\Models\KrsPlan;
use App\Models\KrsPlanItem;
use App\Models\User;

test('user can send and accept friendship by email', function () {
    $requester = User::factory()->create();
    $addressee = User::factory()->create(['email' => 'teman@example.com']);

    $this->actingAs($requester)
        ->post(route('friends.store'), ['email' => 'teman@example.com'])
        ->assertRedirect();

    $friendship = Friendship::query()->first();

    expect($friendship)->not->toBeNull()
        ->and($friendship->status)->toBe(FriendshipStatus::Pending);

    $this->actingAs($addressee)
        ->post(route('friends.accept', $friendship))
        ->assertRedirect();

    expect($friendship->fresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and($requester->isFriendsWith($addressee))->toBeTrue();
});

test('friends index loads for a student with no friendships', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('friends.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('friends/Index')
            ->has('friends', 0)
            ->has('incoming', 0)
            ->has('outgoing', 0)
            ->has('sharedPlans', 0)
        );
});

test('friends index shows shared plans from accepted friends', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create([
        'requester_id' => $user->id,
        'addressee_id' => $friend->id,
    ]);

    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($friend)->for($offering)->create([
        'name' => 'Rencana Teman',
        'is_shared_with_friends' => true,
    ]);

    $this->actingAs($user)
        ->get(route('friends.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('friends/Index')
            ->has('friends', 1)
            ->has('sharedPlans', 1)
            ->where('sharedPlans.0.id', $plan->id)
            ->where('sharedPlans.0.name', 'Rencana Teman')
        );
});

test('friend cannot view unshared plan', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create([
        'requester_id' => $owner->id,
        'addressee_id' => $friend->id,
    ]);

    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($owner)->for($offering)->create([
        'is_shared_with_friends' => false,
    ]);

    $this->actingAs($friend)
        ->get(route('krs.planner', [$offering, $plan]))
        ->assertForbidden();
});

test('friend can view shared plan', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create([
        'requester_id' => $owner->id,
        'addressee_id' => $friend->id,
    ]);

    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($owner)->for($offering)->create([
        'is_shared_with_friends' => true,
    ]);

    $this->actingAs($friend)
        ->get(route('krs.planner', [$offering, $plan]))
        ->assertOk();
});

test('friend can copy shared plan sections into own plan', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create([
        'requester_id' => $owner->id,
        'addressee_id' => $friend->id,
    ]);

    $offering = CourseOffering::factory()->create();
    $course = Course::factory()->for($offering)->create();
    $section = CourseSection::factory()->for($course)->create();
    $source = KrsPlan::factory()->for($owner)->for($offering)->create([
        'is_shared_with_friends' => true,
    ]);
    KrsPlanItem::factory()->for($source)->create([
        'course_section_id' => $section->id,
    ]);

    $this->actingAs($friend)
        ->post(route('krs.plans.copy', $source))
        ->assertRedirect();

    $copied = KrsPlan::query()
        ->where('user_id', $friend->id)
        ->where('course_offering_id', $offering->id)
        ->latest('id')
        ->first();

    expect($copied)->not->toBeNull()
        ->and($copied->items)->toHaveCount(1)
        ->and($copied->items->first()->course_section_id)->toBe($section->id);
});

test('owner can toggle plan share with friends', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create([
        'is_shared_with_friends' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('krs.plans.update', $plan), [
            'is_shared_with_friends' => true,
        ])
        ->assertRedirect();

    expect($plan->fresh()->is_shared_with_friends)->toBeTrue();
});
