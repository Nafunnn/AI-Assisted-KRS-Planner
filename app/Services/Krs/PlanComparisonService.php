<?php

namespace App\Services\Krs;

use App\Enums\DayOfWeek;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\KrsPlanItem;
use Illuminate\Support\Collection;

class PlanComparisonService
{
    public function __construct(
        private ScheduleConflictDetector $conflictDetector,
    ) {}

    /**
     * @return array{
     *     plan_a: array<string, mixed>,
     *     plan_b: array<string, mixed>,
     *     same_sections: list<array<string, mixed>>,
     *     only_a: list<array<string, mixed>>,
     *     only_b: list<array<string, mixed>>,
     *     time_overlaps: list<array<string, mixed>>,
     *     stats: array<string, int>,
     *     calendar_blocks: list<array<string, mixed>>,
     *     grid_config: array<string, mixed>
     * }
     */
    public function compare(KrsPlan $planA, KrsPlan $planB): array
    {
        $planA->loadMissing([
            'user',
            'items.courseSection.schedules',
            'items.courseSection.course',
        ]);
        $planB->loadMissing([
            'user',
            'items.courseSection.schedules',
            'items.courseSection.course',
        ]);

        /** @var Collection<int, KrsPlanItem> $itemsA */
        $itemsA = $planA->items->keyBy('course_section_id');
        /** @var Collection<int, KrsPlanItem> $itemsB */
        $itemsB = $planB->items->keyBy('course_section_id');

        $idsA = $itemsA->keys()->all();
        $idsB = $itemsB->keys()->all();
        $sameIds = array_values(array_intersect($idsA, $idsB));
        $onlyAIds = array_values(array_diff($idsA, $idsB));
        $onlyBIds = array_values(array_diff($idsB, $idsA));

        $sameSections = array_map(
            fn (int $id) => $this->transformSection($itemsA->get($id)->courseSection),
            $sameIds,
        );
        $onlyA = array_map(
            fn (int $id) => $this->transformSection($itemsA->get($id)->courseSection),
            $onlyAIds,
        );
        $onlyB = array_map(
            fn (int $id) => $this->transformSection($itemsB->get($id)->courseSection),
            $onlyBIds,
        );

        $timeOverlaps = $this->buildTimeOverlaps($itemsA, $itemsB, $sameIds);
        $calendarBlocks = $this->buildCalendarBlocks($itemsA, $itemsB, $sameIds, $timeOverlaps);

        $sameSks = collect($sameSections)->sum('sks');

        return [
            'plan_a' => $this->transformPlanSummary($planA),
            'plan_b' => $this->transformPlanSummary($planB),
            'same_sections' => $sameSections,
            'only_a' => $onlyA,
            'only_b' => $onlyB,
            'time_overlaps' => $timeOverlaps,
            'stats' => [
                'same_count' => count($sameSections),
                'only_a_count' => count($onlyA),
                'only_b_count' => count($onlyB),
                'time_overlap_count' => count($timeOverlaps),
                'sks_a' => $planA->totalSks(),
                'sks_b' => $planB->totalSks(),
                'same_sks' => (int) $sameSks,
            ],
            'calendar_blocks' => $calendarBlocks,
            'grid_config' => $this->gridConfig(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPlanSummary(KrsPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'offering_id' => $plan->course_offering_id,
            'total_sks' => $plan->totalSks(),
            'course_count' => $plan->items->unique(fn (KrsPlanItem $item) => $item->courseSection->course_id)->count(),
            'owner' => [
                'id' => $plan->user->id,
                'name' => $plan->user->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformSection(CourseSection $section): array
    {
        $section->loadMissing(['course', 'schedules']);

        return [
            'section_id' => $section->id,
            'course_id' => $section->course_id,
            'code' => $section->course->code,
            'name' => $section->course->name,
            'sks' => $section->course->sks,
            'group_code' => $section->group_code,
            'time_period' => $section->time_period->value,
            'time_period_label' => $section->time_period->label(),
            'schedules' => $section->schedules->map(fn ($schedule) => [
                'day' => $schedule->day->value,
                'day_label' => $schedule->day->label(),
                'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                'ends_at' => substr((string) $schedule->ends_at, 0, 5),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, KrsPlanItem>  $itemsA
     * @param  Collection<int, KrsPlanItem>  $itemsB
     * @param  list<int>  $sameIds
     * @return list<array<string, mixed>>
     */
    private function buildTimeOverlaps(Collection $itemsA, Collection $itemsB, array $sameIds): array
    {
        $overlaps = [];

        foreach ($itemsA as $itemA) {
            $sectionA = $itemA->courseSection;

            foreach ($itemsB as $itemB) {
                $sectionB = $itemB->courseSection;

                if ($sectionA->id === $sectionB->id) {
                    continue;
                }

                if (in_array($sectionA->id, $sameIds, true) && in_array($sectionB->id, $sameIds, true)) {
                    continue;
                }

                foreach ($this->conflictDetector->detailedOverlaps($sectionA, $sectionB) as $overlap) {
                    $overlaps[] = [
                        'day' => $overlap['day'],
                        'day_label' => $overlap['day_label'],
                        'overlap_starts_at' => $overlap['overlap_starts_at'],
                        'overlap_ends_at' => $overlap['overlap_ends_at'],
                        'overlap_minutes' => $overlap['overlap_minutes'],
                        'section_a' => [
                            'section_id' => $sectionA->id,
                            'course_id' => $sectionA->course_id,
                            'code' => $sectionA->course->code,
                            'name' => $sectionA->course->name,
                            'group_code' => $sectionA->group_code,
                            'starts_at' => $overlap['a_starts_at'],
                            'ends_at' => $overlap['a_ends_at'],
                        ],
                        'section_b' => [
                            'section_id' => $sectionB->id,
                            'course_id' => $sectionB->course_id,
                            'code' => $sectionB->course->code,
                            'name' => $sectionB->course->name,
                            'group_code' => $sectionB->group_code,
                            'starts_at' => $overlap['b_starts_at'],
                            'ends_at' => $overlap['b_ends_at'],
                        ],
                    ];
                }
            }
        }

        return $overlaps;
    }

    /**
     * @param  Collection<int, KrsPlanItem>  $itemsA
     * @param  Collection<int, KrsPlanItem>  $itemsB
     * @param  list<int>  $sameIds
     * @param  list<array<string, mixed>>  $timeOverlaps
     * @return list<array<string, mixed>>
     */
    private function buildCalendarBlocks(
        Collection $itemsA,
        Collection $itemsB,
        array $sameIds,
        array $timeOverlaps,
    ): array {
        $overlapSectionIds = collect($timeOverlaps)
            ->flatMap(fn (array $overlap) => [
                $overlap['section_a']['section_id'],
                $overlap['section_b']['section_id'],
            ])
            ->unique()
            ->all();

        $blocks = [];
        $seenSame = [];

        foreach ($itemsA as $item) {
            $section = $item->courseSection;
            $isSame = in_array($section->id, $sameIds, true);

            if ($isSame) {
                if (isset($seenSame[$section->id])) {
                    continue;
                }
                $seenSame[$section->id] = true;
            }

            foreach ($section->schedules as $schedule) {
                $blocks[] = [
                    'plan' => $isSame ? 'both' : 'a',
                    'course_id' => $section->course_id,
                    'section_id' => $section->id,
                    'code' => $section->course->code,
                    'name' => $section->course->name,
                    'group_code' => $section->group_code,
                    'day' => $schedule->day->value,
                    'day_label' => $schedule->day->label(),
                    'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                    'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                    'has_time_overlap' => in_array($section->id, $overlapSectionIds, true),
                ];
            }
        }

        foreach ($itemsB as $item) {
            $section = $item->courseSection;

            if (in_array($section->id, $sameIds, true)) {
                continue;
            }

            foreach ($section->schedules as $schedule) {
                $blocks[] = [
                    'plan' => 'b',
                    'course_id' => $section->course_id,
                    'section_id' => $section->id,
                    'code' => $section->course->code,
                    'name' => $section->course->name,
                    'group_code' => $section->group_code,
                    'day' => $schedule->day->value,
                    'day_label' => $schedule->day->label(),
                    'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                    'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                    'has_time_overlap' => in_array($section->id, $overlapSectionIds, true),
                ];
            }
        }

        return $blocks;
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
            ])->values()->all(),
            'start_hour' => '07:00',
            'end_hour' => '21:00',
            'slot_minutes' => 30,
        ];
    }
}
