<?php

use App\Enums\DayOfWeek;
use App\Enums\FriendshipStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\Friendship;
use App\Models\KrsPlan;
use App\Models\KrsPlanItem;
use App\Models\SectionSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot compare plans', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $planA = KrsPlan::factory()->for($user)->for($offering)->create();
    $planB = KrsPlan::factory()->for($user)->for($offering)->create();

    $this->get(route('krs.plans.compare', [
        'plan_a' => $planA->id,
        'plan_b' => $planB->id,
    ]))->assertRedirect(route('login'));
});

test('user can compare two of their own plans', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $courseShared = Course::factory()->for($offering)->create(['code' => 'IF101', 'sks' => 3]);
    $courseOnlyA = Course::factory()->for($offering)->create(['code' => 'IF102', 'sks' => 2]);
    $courseOnlyB = Course::factory()->for($offering)->create(['code' => 'IF103', 'sks' => 2]);

    $sectionShared = CourseSection::factory()->for($courseShared)->create(['group_code' => 'A']);
    $sectionOnlyA = CourseSection::factory()->for($courseOnlyA)->create(['group_code' => 'A']);
    $sectionOnlyB = CourseSection::factory()->for($courseOnlyB)->create(['group_code' => 'A']);
    $sectionClashB = CourseSection::factory()->for($courseOnlyB)->create(['group_code' => 'B']);

    SectionSchedule::factory()->for($sectionShared)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:00:00',
    ]);
    SectionSchedule::factory()->for($sectionOnlyA)->create([
        'day' => DayOfWeek::Tuesday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
    ]);
    SectionSchedule::factory()->for($sectionOnlyB)->create([
        'day' => DayOfWeek::Wednesday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
    ]);
    // Clash: onlyA Tuesday 08-10 vs clashB Tuesday 09-11
    SectionSchedule::factory()->for($sectionClashB)->create([
        'day' => DayOfWeek::Tuesday,
        'starts_at' => '09:00:00',
        'ends_at' => '11:00:00',
    ]);

    $planA = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana A']);
    $planB = KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Rencana B']);

    KrsPlanItem::factory()->for($planA)->create(['course_section_id' => $sectionShared->id]);
    KrsPlanItem::factory()->for($planA)->create(['course_section_id' => $sectionOnlyA->id]);
    KrsPlanItem::factory()->for($planB)->create(['course_section_id' => $sectionShared->id]);
    KrsPlanItem::factory()->for($planB)->create(['course_section_id' => $sectionClashB->id]);

    $this->actingAs($user)
        ->get(route('krs.plans.compare', [
            'plan_a' => $planA->id,
            'plan_b' => $planB->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('krs/Compare')
            ->where('stats.same_count', 1)
            ->where('stats.only_a_count', 1)
            ->where('stats.only_b_count', 1)
            ->where('stats.time_overlap_count', 1)
            ->where('same_sections.0.code', 'IF101')
            ->where('only_a.0.code', 'IF102')
            ->where('only_b.0.code', 'IF103')
            ->has('calendar_blocks')
            ->has('grid_config')
        );
});

test('friend can compare with shared plan', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->create([
        'requester_id' => $friend->id,
        'addressee_id' => $owner->id,
        'status' => FriendshipStatus::Accepted,
    ]);

    $offering = CourseOffering::factory()->create();
    $ownerPlan = KrsPlan::factory()->for($owner)->for($offering)->create([
        'is_shared_with_friends' => true,
    ]);
    $myPlan = KrsPlan::factory()->for($friend)->for($offering)->create();

    $this->actingAs($friend)
        ->get(route('krs.plans.compare', [
            'plan_a' => $myPlan->id,
            'plan_b' => $ownerPlan->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('krs/Compare'));
});

test('cannot compare with unshared friend plan', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create([
        'requester_id' => $friend->id,
        'addressee_id' => $owner->id,
    ]);

    $offering = CourseOffering::factory()->create();
    $ownerPlan = KrsPlan::factory()->for($owner)->for($offering)->create([
        'is_shared_with_friends' => false,
    ]);
    $myPlan = KrsPlan::factory()->for($friend)->for($offering)->create();

    $this->actingAs($friend)
        ->get(route('krs.plans.compare', [
            'plan_a' => $myPlan->id,
            'plan_b' => $ownerPlan->id,
        ]))
        ->assertForbidden();
});

test('cannot compare plans from different offerings', function () {
    $user = User::factory()->create();
    $offeringA = CourseOffering::factory()->create();
    $offeringB = CourseOffering::factory()->create();
    $planA = KrsPlan::factory()->for($user)->for($offeringA)->create();
    $planB = KrsPlan::factory()->for($user)->for($offeringB)->create();

    $this->actingAs($user)
        ->get(route('krs.plans.compare', [
            'plan_a' => $planA->id,
            'plan_b' => $planB->id,
        ]))
        ->assertSessionHasErrors('plan_b');
});

test('friends index includes myPlans for compare dialog', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    KrsPlan::factory()->for($user)->for($offering)->create(['name' => 'Punyaku']);

    $this->actingAs($user)
        ->get(route('friends.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('friends/Index')
            ->has('myPlans', 1)
            ->where('myPlans.0.name', 'Punyaku')
        );
});
