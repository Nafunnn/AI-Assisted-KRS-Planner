<?php

use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('guests cannot import course offerings', function () {
    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $this->post(route('krs.offerings.store'), [
        'file' => $file,
    ])->assertRedirect(route('login'));
});

test('authenticated user can import course offering from excel', function () {
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('tests/Fixtures/semester5-offering.xlsx'),
        'semester5-offering.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $response = $this->actingAs($user)
        ->post(route('krs.offerings.store'), [
            'file' => $file,
            'title' => 'Semester 5',
        ]);

    $offering = CourseOffering::query()->where('user_id', $user->id)->first();

    expect($offering)->not->toBeNull()
        ->and($offering->title)->toBe('Semester 5')
        ->and($offering->courses()->count())->toBe(8)
        ->and($offering->krsPlans()->count())->toBe(1);

    $response->assertRedirect(route('krs.planner', [$offering, $offering->latestPlan]));
});

test('user cannot view another users offering', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $offering = CourseOffering::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('krs.offerings.show', $offering))
        ->assertForbidden();
});
