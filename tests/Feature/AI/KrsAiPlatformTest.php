<?php

use App\AI\Context\ContextBuilder;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\AiConfigResolver;
use App\AI\Services\PermissionGuard;
use App\Enums\DayOfWeek;
use App\Models\AiProviderConfig;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\SectionSchedule;
use App\Models\User;
use App\Services\Krs\KrsPlanItemSyncer;
use App\Services\Krs\KrsScheduleGenerator;

test('user can store 9router ai provider config', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => '9router',
            'name' => '9Router Local',
            'api_key' => '9router',
            'default_model' => 'claude-sonnet-4',
            'is_active' => true,
        ])
        ->assertRedirect(route('ai-providers.edit'));

    $config = AiProviderConfig::query()->where('user_id', $user->id)->first();

    expect($config)->not->toBeNull()
        ->and($config->provider->value)->toBe('9router')
        ->and($config->is_active)->toBeTrue();
});

test('ai chat returns unavailable without active provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), [
            'message' => 'Review jadwal saya',
        ])
        ->assertSuccessful()
        ->assertJson(['status' => 'unavailable']);
});

test('detect plan conflicts tool summarizes plan via syncer', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $course = Course::factory()->for($offering)->create(['sks' => 3]);
    $section = CourseSection::factory()->for($course)->create();
    SectionSchedule::factory()->for($section)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
    ]);
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();
    $plan->items()->create(['course_section_id' => $section->id]);

    $syncer = app(KrsPlanItemSyncer::class);
    $summary = $syncer->summarize($plan, [$section->id]);

    expect($summary['has_conflicts'])->toBeFalse()
        ->and($summary['total_sks'])->toBe(3)
        ->and($summary['course_count'])->toBe(1);
});

test('schedule generator produces conflict free preview', function () {
    $user = User::factory()->create();
    $offering = CourseOffering::factory()->create();
    $courseA = Course::factory()->for($offering)->create(['code' => 'MKA', 'sks' => 3]);
    $courseB = Course::factory()->for($offering)->create(['code' => 'MKB', 'sks' => 2]);
    $sectionA = CourseSection::factory()->for($courseA)->create();
    $sectionB = CourseSection::factory()->for($courseB)->create();
    SectionSchedule::factory()->for($sectionA)->create([
        'day' => DayOfWeek::Monday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
    ]);
    SectionSchedule::factory()->for($sectionB)->create([
        'day' => DayOfWeek::Tuesday,
        'starts_at' => '08:00:00',
        'ends_at' => '10:00:00',
    ]);
    $plan = KrsPlan::factory()->for($user)->for($offering)->create();

    $generator = app(KrsScheduleGenerator::class);
    $result = $generator->generate($plan, ['min_sks' => 5, 'max_sks' => 24], apply: false);

    expect($result['summary']['has_conflicts'])->toBeFalse()
        ->and($result['section_ids'])->toHaveCount(2);
});

test('module registry loads krs entities', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('krs_plan'))->toBeTrue()
        ->and($registry->has('course_offering'))->toBeTrue()
        ->and($registry->has('course_section'))->toBeTrue()
        ->and($registry->has('section_schedule'))->toBeTrue()
        ->and($registry->has('course'))->toBeTrue()
        ->and($registry->has('krs_plan_item'))->toBeTrue();
});

test('krs entities expose rich metadata for ai context', function () {
    $registry = app(ModuleRegistry::class);
    $course = $registry->get('course');
    $section = $registry->get('course_section');

    expect($course)->not->toBeNull()
        ->and($course->fields)->toHaveKey('code')
        ->and($course->fields['code']['type'])->toBe('string')
        ->and($course->businessRules)->not->toBeEmpty()
        ->and($section->relations)->toHaveKey('schedules')
        ->and($section->fields['time_period']['values'])->toHaveKey('P');
});

test('context builder includes entity fields in prompt context', function () {
    $user = User::factory()->create();
    AiProviderConfig::factory()->for($user)->create(['is_active' => true]);

    $settings = app(AiConfigResolver::class)->settingsForUser($user);
    $context = app(ContextBuilder::class)->build($user, $settings);

    expect($context['entities']['course']['fields'])->toHaveKey('sks')
        ->and($context['entities']['krs_plan']['computed'])->toHaveKey('total_sks')
        ->and($context['business_context']['entity_hierarchy'])->toHaveKey('section_schedule');
});

test('permission guard allows krs entities for authenticated user', function () {
    $user = User::factory()->create();
    $guard = app(PermissionGuard::class);

    expect($guard->canUseAiAssistant($user))->toBeFalse()
        ->and($guard->allowedEntityKeys($user))->toContain('krs_plan');
});
