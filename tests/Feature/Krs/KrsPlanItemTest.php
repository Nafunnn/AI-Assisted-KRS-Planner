<?php

use App\Enums\DayOfWeek;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\SectionSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can add non conflicting section to plan', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $courseA = Course::factory()->for($offering)->create();
    $courseB = Course::factory()->for($offering)->create();

    $sectionA = CourseSection::factory()->for($courseA)->create();
    $sectionB = CourseSection::factory()->for($courseB)->create();

    SectionSchedule::factory()->for($sectionA)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($sectionB)->create([
        'day' => DayOfWeek::Tuesday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionA->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionB->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    expect($plan->fresh()->items)->toHaveCount(2);
});

test('adding conflicting section is rejected', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $courseA = Course::factory()->for($offering)->create();
    $courseB = Course::factory()->for($offering)->create();

    $sectionA = CourseSection::factory()->for($courseA)->create();
    $sectionB = CourseSection::factory()->for($courseB)->create();

    SectionSchedule::factory()->for($sectionA)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($sectionB)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
    ]);

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionA->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionB->id,
            'action' => 'add',
        ])
        ->assertUnprocessable()
        ->assertJson(['conflicts' => true]);
});

test('adding section replaces existing section for same course', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $course = Course::factory()->for($offering)->create();
    $sectionA = CourseSection::factory()->for($course)->create(['group_code' => 'A11.4501']);
    $sectionB = CourseSection::factory()->for($course)->create(['group_code' => 'A11.4502']);

    SectionSchedule::factory()->for($sectionA)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($sectionB)->create([
        'day' => DayOfWeek::Tuesday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionA->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionB->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    $plan->refresh();

    expect($plan->items)->toHaveCount(1)
        ->and($plan->items->first()->course_section_id)->toBe($sectionB->id);
});

test('adding a section returns overlapping groups from other courses as unavailable', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $courseA = Course::factory()->for($offering)->create();
    $courseB = Course::factory()->for($offering)->create();

    $sectionA = CourseSection::factory()->for($courseA)->create();
    $sectionB = CourseSection::factory()->for($courseB)->create();

    SectionSchedule::factory()->for($sectionA)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($sectionB)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $sectionA->id,
            'action' => 'add',
        ])
        ->assertSuccessful()
        ->assertJsonPath('plan.unavailable_section_ids', [$sectionB->id])
        ->assertJsonPath('plan.unavailable_sections.0.section_id', $sectionB->id)
        ->assertJsonPath('plan.unavailable_sections.0.conflicts_with.0.section_id', $sectionA->id)
        ->assertJsonPath('plan.unavailable_sections.0.conflicts_with.0.course_code', $courseA->code)
        ->assertJsonPath('plan.unavailable_sections.0.conflicts_with.0.course_name', $courseA->name)
        ->assertJsonPath('plan.unavailable_sections.0.conflicts_with.0.group_code', $sectionA->group_code)
        ->assertJsonPath('plan.selected_course_ids', [$courseA->id]);
});

test('planner grid covers 07:00 to 21:00', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $this->actingAs($user)
        ->get(route('krs.planner', [$offering, $plan]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('krs/Planner')
            ->where('gridConfig.start_hour', '07:00')
            ->where('gridConfig.end_hour', '21:00')
        );
});

test('planner can refresh calendar with a partial reload of plan data', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $this->actingAs($user)
        ->get(route('krs.planner', [$offering, $plan]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('krs/Planner')
            ->has('plan')
            ->has('plans')
            ->reloadOnly(['plan', 'plans'], fn (Assert $reload) => $reload
                ->where('plan.id', $plan->id)
                ->has('plans', 1)
                ->missing('offering')
                ->missing('gridConfig')
            )
        );
});
