<?php

use App\Enums\DayOfWeek;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\SectionSchedule;
use App\Services\Krs\ScheduleConflictDetector;

test('detects overlapping schedules on the same day', function () {
    $offering = CourseOffering::factory()->create();
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
        'starts_at' => '09:00:00',
        'ends_at' => '12:00:00',
    ]);

    $detector = app(ScheduleConflictDetector::class);
    $conflicts = $detector->detect(collect([$sectionA->fresh('schedules'), $sectionB->fresh('schedules')]));

    expect($conflicts)->toHaveCount(1);
});

test('does not detect conflict on different days', function () {
    $offering = CourseOffering::factory()->create();
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

    $detector = app(ScheduleConflictDetector::class);
    $conflicts = $detector->detect(collect([$sectionA->fresh('schedules'), $sectionB->fresh('schedules')]));

    expect($conflicts)->toBeEmpty();
});

test('marks other course sections with overlapping schedules as unavailable', function () {
    $offering = CourseOffering::factory()->create();
    $courseA = Course::factory()->for($offering)->create();
    $courseB = Course::factory()->for($offering)->create();

    $selected = CourseSection::factory()->for($courseA)->create();
    $overlapping = CourseSection::factory()->for($courseB)->create();
    $free = CourseSection::factory()->for($courseB)->create();

    SectionSchedule::factory()->for($selected)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($overlapping)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($free)->create([
        'day' => DayOfWeek::Wednesday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    $detector = app(ScheduleConflictDetector::class);
    $unavailable = $detector->unavailableSectionIds(
        collect([$selected->fresh(['schedules'])]),
        collect([$selected, $overlapping, $free])->map->fresh(['schedules']),
    );

    expect($unavailable)->toContain($overlapping->id)
        ->and($unavailable)->not->toContain($selected->id)
        ->and($unavailable)->not->toContain($free->id);
});

test('does not mark same course alternative sections as unavailable', function () {
    $offering = CourseOffering::factory()->create();
    $course = Course::factory()->for($offering)->create();

    $selected = CourseSection::factory()->for($course)->create();
    $alternative = CourseSection::factory()->for($course)->create();

    SectionSchedule::factory()->for($selected)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    SectionSchedule::factory()->for($alternative)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '07:00:00',
        'ends_at' => '09:30:00',
    ]);

    $detector = app(ScheduleConflictDetector::class);
    $unavailable = $detector->unavailableSectionIds(
        collect([$selected->fresh(['schedules'])]),
        collect([$selected, $alternative])->map->fresh(['schedules']),
    );

    expect($unavailable)->toBeEmpty();
});
