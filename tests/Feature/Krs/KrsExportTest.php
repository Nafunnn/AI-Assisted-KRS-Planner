<?php

use App\Models\KrsPlan;
use App\Models\User;
use App\Services\Krs\CourseOfferingImportService;
use Illuminate\Http\UploadedFile;

test('user can export krs plan as pdf', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $offering = app(CourseOfferingImportService::class)
        ->create($admin, $file, 'Semester 5', '2025/2026-gasal')['offering'];

    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $response = $this->actingAs($user)
        ->get(route('krs.plans.export.pdf', $plan));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('end to end published catalog planner and export flow', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $offering = app(CourseOfferingImportService::class)
        ->create($admin, $file, 'Semester 5 E2E', '2025/2026-gasal')['offering'];

    $this->actingAs($user)
        ->get(route('krs.planner.latest', $offering))
        ->assertRedirect();

    $plan = KrsPlan::query()->where('user_id', $user->id)->where('course_offering_id', $offering->id)->firstOrFail();

    $this->actingAs($user)
        ->get(route('krs.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('krs.planner', [$offering, $plan]))
        ->assertOk();

    $section = $offering->courses()->first()->sections()->first();

    $this->actingAs($user)
        ->postJson(route('krs.plans.items.toggle', $plan), [
            'course_section_id' => $section->id,
            'action' => 'add',
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('krs.plans.export.pdf', $plan))
        ->assertSuccessful();
});
