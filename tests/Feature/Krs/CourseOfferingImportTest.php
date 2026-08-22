<?php

use App\Enums\DayOfWeek;
use App\Enums\KrsPlanItemStatus;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\KrsPlanItem;
use App\Models\User;
use App\Services\Krs\CourseOfferingImportService;
use Illuminate\Http\UploadedFile;

test('guests cannot import course offerings', function () {
    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->post(route('krs.admin.offerings.store'), [
        'file' => $file,
    ])->assertRedirect(route('login'));
});

test('non admin cannot import course offerings', function () {
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->actingAs($user)
        ->post(route('krs.admin.offerings.store'), [
            'file' => $file,
            'title' => 'Semester 5',
        ])
        ->assertForbidden();
});

test('admin can import course offering from excel', function () {
    $admin = User::factory()->admin()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $response = $this->actingAs($admin)
        ->post(route('krs.admin.offerings.store'), [
            'file' => $file,
            'title' => 'Semester 5',
            'term' => '2025/2026-gasal',
        ]);

    $offering = CourseOffering::query()->first();

    expect($offering)->not->toBeNull()
        ->and($offering->title)->toBe('Semester 5')
        ->and($offering->term)->toBe('2025/2026-gasal')
        ->and($offering->published_at)->not->toBeNull()
        ->and($offering->courses()->count())->toBe(8)
        ->and($offering->krsPlans()->count())->toBe(0);

    $response->assertRedirect(route('krs.admin.offerings.index'));
});

test('student can view published offering', function () {
    $student = User::factory()->create();
    $offering = CourseOffering::factory()->create();

    $this->actingAs($student)
        ->get(route('krs.offerings.show', $offering))
        ->assertSuccessful();
});

test('student cannot view unpublished offering', function () {
    $student = User::factory()->create();
    $offering = CourseOffering::factory()->unpublished()->create();

    $this->actingAs($student)
        ->get(route('krs.offerings.show', $offering))
        ->assertForbidden();
});

test('admin sync preserves section ids and marks stale plan items', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $importService = app(CourseOfferingImportService::class);
    $result = $importService->create($admin, $file, 'Semester 5', '2025/2026-gasal');
    $offering = $result['offering'];

    $section = CourseSection::query()
        ->whereHas('course', fn ($q) => $q->where('course_offering_id', $offering->id))
        ->with('schedules')
        ->firstOrFail();

    $sectionId = $section->id;

    $plan = KrsPlan::factory()->for($student)->for($offering)->create();
    KrsPlanItem::factory()->for($plan)->create([
        'course_section_id' => $sectionId,
        'status' => KrsPlanItemStatus::Active,
        'schedule_fingerprint' => $section->scheduleFingerprint(),
    ]);

    $syncFile = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    // Change schedule by deleting and recreating via sync of same file keeps fingerprint —
    // manually alter schedule then sync same excel to restore and mark changed.
    $section->schedules()->delete();
    $section->schedules()->create([
        'slot_number' => 1,
        'day' => DayOfWeek::Monday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
        'raw' => 'SENIN, 08:00:00 - 10:00:00',
    ]);

    $syncResult = $importService->sync($admin, $offering->fresh(), $syncFile, dryRun: false);

    expect(CourseSection::query()->find($sectionId))->not->toBeNull()
        ->and($syncResult['offering']->catalog_version)->toBe(2);

    $item = KrsPlanItem::query()->where('krs_plan_id', $plan->id)->first();

    expect($item->course_section_id)->toBe($sectionId)
        ->and($item->status)->toBe(KrsPlanItemStatus::ScheduleChanged);
});

test('admin sync dry run does not persist catalog version bump', function () {
    $admin = User::factory()->admin()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $importService = app(CourseOfferingImportService::class);
    $offering = $importService->create($admin, $file, 'Semester 5', '2025/2026-gasal')['offering'];

    $preview = $importService->sync($admin, $offering, $file, dryRun: true);

    expect($preview['dry_run'])->toBeTrue()
        ->and($offering->fresh()->catalog_version)->toBe(1);
});
