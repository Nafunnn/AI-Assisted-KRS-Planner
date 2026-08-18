<?php

namespace App\Http\Controllers\Krs;

use App\Enums\DayOfWeek;
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

        $plan = $offering->krsPlans()->latest('id')->first();

        if ($plan === null) {
            $plan = $offering->krsPlans()->create([
                'user_id' => $request->user()->id,
                'name' => $offering->nextPlanName(),
                'status' => KrsPlanStatus::Draft,
            ]);
        }

        return redirect()->route('krs.planner', [$offering, $plan]);
    }

    public function planner(CourseOffering $offering, KrsPlan $plan): Response
    {
        $this->authorize('view', $offering);
        $this->authorize('view', $plan);
        abort_unless($plan->course_offering_id === $offering->id, 404);

        $offering->load(['courses.sections.schedules']);
        $plan->load([
            'items.courseSection.schedules',
            'items.courseSection.course',
        ]);

        $selectedSections = $plan->items->map(fn ($item) => $item->courseSection);
        $conflicts = $this->conflictDetector->detect($selectedSections);
        $unavailableSectionIds = $this->conflictDetector->unavailableSectionIds(
            $selectedSections,
            $offering->courses->flatMap(fn ($course) => $course->sections),
        );

        return Inertia::render('krs/Planner', [
            'offering' => $this->offeringController->transformOffering($offering),
            'plan' => $this->transformPlan($plan, $conflicts, $unavailableSectionIds),
            'plans' => $this->transformPlanSummaries($offering),
            'gridConfig' => $this->gridConfig(),
        ]);
    }

    public function store(StoreKrsPlanRequest $request, CourseOffering $offering): RedirectResponse
    {
        $plan = $offering->krsPlans()->create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name') ?: $offering->nextPlanName(),
            'status' => KrsPlanStatus::Draft,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rencana baru dibuat.']);

        return redirect()->route('krs.planner', [$offering, $plan]);
    }

    public function update(UpdateKrsPlanRequest $request, KrsPlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nama rencana diperbarui.']);

        return redirect()->route('krs.planner', [$plan->courseOffering, $plan]);
    }

    public function destroy(KrsPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $offering = $plan->courseOffering;

        if ($offering->krsPlans()->count() <= 1) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Minimal satu rencana harus tersisa untuk penawaran ini.',
            ]);

            return back();
        }

        $plan->delete();

        $next = $offering->krsPlans()->latest('id')->firstOrFail();

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
        ]);

        return response()->json([
            'plan' => $this->refreshPlanPayload($plan),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $conflicts
     * @param  list<int>  $unavailableSectionIds
     * @return array<string, mixed>
     */
    private function transformPlan(KrsPlan $plan, array $conflicts, array $unavailableSectionIds = []): array
    {
        $selectedSectionIds = $plan->items->pluck('course_section_id')->all();
        $conflictSectionIds = collect($conflicts)
            ->flatMap(fn (array $conflict) => [$conflict['section_a_id'], $conflict['section_b_id']])
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'status' => $plan->status->value,
            'total_sks' => $plan->totalSks(),
            'selected_section_ids' => $selectedSectionIds,
            'selected_course_ids' => $plan->items
                ->map(fn ($item) => $item->courseSection->course_id)
                ->unique()
                ->values()
                ->all(),
            'unavailable_section_ids' => $unavailableSectionIds,
            'course_count' => $plan->items->unique(fn ($item) => $item->courseSection->course_id)->count(),
            'has_conflicts' => $conflicts !== [],
            'conflict_section_ids' => $conflictSectionIds,
            'conflicts' => $conflicts,
            'items' => $plan->items->map(fn ($item) => [
                'id' => $item->id,
                'course_section_id' => $item->course_section_id,
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
                    'schedules' => $item->courseSection->schedules->map(fn ($schedule) => [
                        'day' => $schedule->day->value,
                        'day_label' => $schedule->day->label(),
                        'starts_at' => substr($schedule->starts_at, 0, 5),
                        'ends_at' => substr($schedule->ends_at, 0, 5),
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
        ]);

        $selectedSections = $plan->items->map(fn ($item) => $item->courseSection);
        $conflicts = $this->conflictDetector->detect($selectedSections);
        $unavailableSectionIds = $this->conflictDetector->unavailableSectionIds(
            $selectedSections,
            $plan->courseOffering->courses->flatMap(fn ($course) => $course->sections),
        );

        return $this->transformPlan($plan, $conflicts, $unavailableSectionIds);
    }

    /**
     * @return list<array{id: int, name: string, items_count: int}>
     */
    private function transformPlanSummaries(CourseOffering $offering): array
    {
        return $offering->krsPlans()
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
