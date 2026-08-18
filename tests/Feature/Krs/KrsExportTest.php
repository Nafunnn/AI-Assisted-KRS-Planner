<?php

use App\Models\CourseOffering;
use App\Models\KrsPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('user can export krs plan as pdf', function () {
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->actingAs($user)
        ->post(route('krs.offerings.store'), [
            'file' => $file,
            'title' => 'Semester 5',
        ]);

    $plan = KrsPlan::query()->where('user_id', $user->id)->firstOrFail();

    $response = $this->actingAs($user)
        ->get(route('krs.plans.export.pdf', $plan));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('end to end import planner and export flow', function () {
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->actingAs($user)
        ->post(route('krs.offerings.store'), [
            'file' => $file,
            'title' => 'Semester 5 E2E',
        ])
        ->assertRedirect();

    $offering = CourseOffering::query()->where('user_id', $user->id)->firstOrFail();
    $plan = $offering->krsPlans()->first();

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
