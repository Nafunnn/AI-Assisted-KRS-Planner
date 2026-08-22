<?php

namespace App\Http\Controllers\Krs;

use App\Enums\DayOfWeek;
use App\Enums\KrsPlanItemStatus;
use App\Enums\KrsPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Krs\StoreKrsPlanRequest;
use App\Http\Requests\Krs\TogglePlanItemRequest;
use App\Http\Requests\Krs\UpdateKrsPlanRequest;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Services\Krs\ScheduleConflictDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KrsPlanController extends Controller
{
    public function __construct(
        private CourseOfferingController $offeringController,
        private ScheduleConflictDetector $conflictDetector,
    ) {}

    public function latest(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->authorize('view', $offering);

        $plan = $offering->krsPlans()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        if ($plan === null) {
            $plan = $offering->krsPlans()->create([
                'user_id' => $request->user()->id,
                'name' => $offering->nextPlanNameFor($request->user()),
                'status' => KrsPlanStatus::Draft,
            ]);
        }

        return redirect()->route('krs.planner', [$offering, $plan]);
    }

    public function planner(Request $request, CourseOffering $offering, KrsPlan $plan): Response
    {
        $this->authorize('view', $offering);
        $this->authorize('view', $plan);
        abort_unless($plan->course_offering_id === $offering->id, 404);

        $isOwner = $request->user()->id === $plan->user_id;

        $offering->load(['courses.sections.schedules', 'courses.sections.course']);
        $plan->load([
            'items.courseSection.schedules',
            'items.courseSection.course',
            'user',
        ]);

        $selectedSections = $plan->items->map(fn ($item) => $item->courseSection);
        $conflicts = $this->conflictDetector->detect($selectedSections);
        $unavailableSections = $this->conflictDetector->unavailableSectionReasons(
            $selectedSections,
            $offering->courses->flatMap(fn ($course) => $course->sections->whereNull('deprecated_at')),
        );

        return Inertia::render('krs/Planner', [
            'offering' => $this->offeringController->transformOffering($offering),
            'plan' => $this->transformPlan($plan, $conflicts, $unavailableSections),
            'plans' => $isOwner ? $this->transformPlanSummaries($offering, $request->user()->id) : [],
            'gridConfig' => $this->gridConfig(),
            'readOnly' => ! $isOwner,
            'owner' => [
                'id' => $plan->user->id,
                'name' => $plan->user->name,
            ],
        ]);
    }

    public function store(StoreKrsPlanRequest $request, CourseOffering $offering): RedirectResponse
    {
        $plan = $offering->krsPlans()->create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name') ?: $offering->nextPlanNameFor($request->user()),
            'status' => KrsPlanStatus::Draft,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rencana baru dibuat.']);

        return redirect()->route('krs.planner', [$offering, $plan]);
    }

    public function update(UpdateKrsPlanRequest $request, KrsPlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rencana diperbarui.']);

        return redirect()->route('krs.planner', [$plan->courseOffering, $plan]);
    }

    public function destroy(Request $request, KrsPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $offering = $plan->courseOffering;
        $userId = $request->user()->id;

        if ($offering->krsPlans()->where('user_id', $userId)->count() <= 1) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Minimal satu rencana harus tersisa untuk katalog ini.',
            ]);

            return back();
        }

        $plan->delete();

        $next = $offering->krsPlans()
            ->where('user_id', $userId)
            ->latest('id')
            ->firstOrFail();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rencana dihapus.']);

        return redirect()->route('krs.planner', [$offering, $next]);
    }

    public function toggleItem(TogglePlanItemRequest $request, KrsPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $section = CourseSection::query()
            ->with(['schedules', 'course'])
            ->findOrFail($request->integer('course_section_id'));

        abort_unless(
            $section->course->course_offering_id === $plan->course_offering_id,
            422,
            'Kelompok tidak termasuk penawaran ini.',
        );

        abort_if(
            $section->isDeprecated(),
            422,
            'Kelompok ini sudah tidak tersedia di katalog.',
        );

        if ($request->string('action')->toString() === 'remove') {
            $plan->items()->where('course_section_id', $section->id)->delete();

            return response()->json([
                'plan' => $this->refreshPlanPayload($plan),
            ]);
        }

        $existingSameCourse = $plan->items()
            ->whereHas('courseSection', fn ($query) => $query->where('course_id', $section->course_id))
            ->with('courseSection')
            ->first();

        $replacingSectionId = $existingSameCourse?->course_section_id;

        if ($this->conflictDetector->wouldConflict($plan, $section, $replacingSectionId)) {
            return response()->json([
                'message' => 'Jadwal bentrok dengan mata kuliah yang sudah dipilih.',
                'conflicts' => true,
            ], 422);
        }

        if ($existingSameCourse !== null) {
            $existingSameCourse->delete();
        }

        $plan->items()->create([
            'course_section_id' => $section->id,
            'status' => KrsPlanItemStatus::Active,
            'schedule_fingerprint' => $section->scheduleFingerprint(),
        ]);

        return response()->json([
            'plan' => $this->refreshPlanPayload($plan),
        ]);
    }

    public function copyFrom(Request $request, KrsPlan $plan): RedirectResponse
    {
        $this->authorize('copyFrom', $plan);

        $offering = $plan->courseOffering;
        $this->authorize('view', $offering);

        $plan->load(['items.courseSection', 'user']);

        $copied = $offering->krsPlans()->create([
            'user_id' => $request->user()->id,
            'name' => 'Salinan dari '.$plan->user->name,
            'status' => KrsPlanStatus::Draft,
        ]);

        foreach ($plan->items as $item) {
            if ($item->courseSection->isDeprecated()) {
                continue;
            }

            if ($item->status === KrsPlanItemStatus::SectionRemoved) {
                continue;
            }

            $copied->items()->create([
                'course_section_id' => $item->course_section_id,
                'status' => KrsPlanItemStatus::Active,
                'schedule_fingerprint' => $item->courseSection->scheduleFingerprint(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rencana teman disalin ke rencana baru.']);

        return redirect()->route('krs.planner', [$offering, $copied]);
    }

    /**
     * @param  list<array<string, mixed>>  $conflicts
     * @param  list<array<string, mixed>>  $unavailableSections
     * @return array<string, mixed>
     */
    private function transformPlan(KrsPlan $plan, array $conflicts, array $unavailableSections = []): array
    {
        $selectedSectionIds = $plan->items->pluck('course_section_id')->all();
        $conflictSectionIds = collect($conflicts)
            ->flatMap(fn (array $conflict) => [$conflict['section_a_id'], $conflict['section_b_id']])
            ->unique()
            ->values()
            ->all();

        $staleItems = $plan->items->filter(
            fn ($item) => in_array($item->status, [KrsPlanItemStatus::ScheduleChanged, KrsPlanItemStatus::SectionRemoved], true),
        );

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'status' => $plan->status->value,
            'is_shared_with_friends' => $plan->is_shared_with_friends,
            'total_sks' => $plan->totalSks(),
            'selected_section_ids' => $selectedSectionIds,
            'selected_course_ids' => $plan->items
                ->map(fn ($item) => $item->courseSection->course_id)
                ->unique()
                ->values()
                ->all(),
            'unavailable_section_ids' => array_column($unavailableSections, 'section_id'),
            'unavailable_sections' => $unavailableSections,
            'course_count' => $plan->items->unique(fn ($item) => $item->courseSection->course_id)->count(),
            'has_conflicts' => $conflicts !== [],
            'has_stale_items' => $staleItems->isNotEmpty(),
            'stale_items_count' => $staleItems->count(),
            'conflict_section_ids' => $conflictSectionIds,
            'conflicts' => $conflicts,
            'items' => $plan->items->map(fn ($item) => [
                'id' => $item->id,
                'course_section_id' => $item->course_section_id,
                'status' => $item->status->value,
                'course' => [
                    'id' => $item->courseSection->course->id,
                    'code' => $item->courseSection->course->code,
                    'name' => $item->courseSection->course->name,
                    'sks' => $item->courseSection->course->sks,
                ],
                'section' => [
                    'id' => $item->courseSection->id,
                    'group_code' => $item->courseSection->group_code,
                    'time_period' => $item->courseSection->time_period->value,
                    'deprecated_at' => $item->courseSection->deprecated_at?->toIso8601String(),
                    'schedules' => $item->courseSection->schedules->map(fn ($schedule) => [
                        'day' => $schedule->day->value,
                        'day_label' => $schedule->day->label(),
                        'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                        'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                    ])->values(),
                ],
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshPlanPayload(KrsPlan $plan): array
    {
        $plan->load([
            'items.courseSection.schedules',
            'items.courseSection.course',
            'courseOffering.courses.sections.schedules',
            'courseOffering.courses.sections.course',
        ]);

        $selectedSections = $plan->items->map(fn ($item) => $item->courseSection);
        $conflicts = $this->conflictDetector->detect($selectedSections);
        $unavailableSections = $this->conflictDetector->unavailableSectionReasons(
            $selectedSections,
            $plan->courseOffering->courses->flatMap(fn ($course) => $course->sections->whereNull('deprecated_at')),
        );

        return $this->transformPlan($plan, $conflicts, $unavailableSections);
    }

    /**
     * @return list<array{id: int, name: string, items_count: int}>
     */
    private function transformPlanSummaries(CourseOffering $offering, int $userId): array
    {
        return $offering->krsPlans()
            ->where('user_id', $userId)
            ->withCount('items')
            ->orderBy('id')
            ->get()
            ->map(fn (KrsPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'items_count' => $plan->items_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function gridConfig(): array
    {
        return [
            'days' => collect(DayOfWeek::weekdays())->map(fn ($day) => [
                'value' => $day->value,
                'label' => $day->shortLabel(),
            ])->values(),
            'start_hour' => '07:00',
            'end_hour' => '21:00',
            'slot_minutes' => 30,
        ];
    }
}
