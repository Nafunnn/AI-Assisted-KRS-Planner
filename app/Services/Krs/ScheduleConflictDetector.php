<?php

namespace App\Services\Krs;

use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\SectionSchedule;
use Illuminate\Support\Collection;

class ScheduleConflictDetector
{
    /**
     * @param  Collection<int, CourseSection>  $sections
     * @return list<array{
     *     section_a_id: int,
     *     section_b_id: int,
     *     day: string,
     *     starts_at: string,
     *     ends_at: string
     * }>
     */
    public function detect(Collection $sections): array
    {
        $conflicts = [];
        $schedules = $sections->flatMap(fn (CourseSection $section) => $section->schedules->map(
            fn (SectionSchedule $schedule) => [
                'section_id' => $section->id,
                'day' => $schedule->day->value,
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
            ],
        ));

        $grouped = $schedules->groupBy('day');

        foreach ($grouped as $daySchedules) {
            $items = $daySchedules->values()->all();

            for ($i = 0; $i < count($items); $i++) {
                for ($j = $i + 1; $j < count($items); $j++) {
                    if ($items[$i]['section_id'] === $items[$j]['section_id']) {
                        continue;
                    }

                    if ($this->overlaps($items[$i]['starts_at'], $items[$i]['ends_at'], $items[$j]['starts_at'], $items[$j]['ends_at'])) {
                        $conflicts[] = [
                            'section_a_id' => $items[$i]['section_id'],
                            'section_b_id' => $items[$j]['section_id'],
                            'day' => $items[$i]['day'],
                            'starts_at' => $this->minTime($items[$i]['starts_at'], $items[$j]['starts_at']),
                            'ends_at' => $this->maxTime($items[$i]['ends_at'], $items[$j]['ends_at']),
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    public function wouldConflict(KrsPlan $plan, CourseSection $newSection, ?int $replacingSectionId = null): bool
    {
        $existingSections = $plan->items()
            ->with('courseSection.schedules')
            ->get()
            ->map(fn ($item) => $item->courseSection)
            ->filter(fn (CourseSection $section) => $replacingSectionId === null || $section->id !== $replacingSectionId);

        $newSection->loadMissing('schedules');

        return $this->detect($existingSections->push($newSection)) !== [];
    }

    /**
     * @param  Collection<int, CourseSection>  $selectedSections
     * @param  Collection<int, CourseSection>  $candidateSections
     * @return list<array{
     *     section_id: int,
     *     conflicts_with: list<array{
     *         section_id: int,
     *         course_code: string,
     *         course_name: string,
     *         group_code: string,
     *         day: string,
     *         day_label: string,
     *         starts_at: string,
     *         ends_at: string
     *     }>
     * }>
     */
    public function unavailableSectionReasons(Collection $selectedSections, Collection $candidateSections): array
    {
        $selectedSections = $selectedSections->filter()->values();

        if ($selectedSections->isEmpty()) {
            return [];
        }

        $selectedSections->each->loadMissing(['schedules', 'course']);
        $candidateSections->each->loadMissing(['schedules', 'course']);

        $selectedSectionIds = $selectedSections->pluck('id')->all();
        $reasons = [];

        foreach ($candidateSections as $candidate) {
            if (in_array($candidate->id, $selectedSectionIds, true)) {
                continue;
            }

            $conflictsWith = [];

            foreach ($selectedSections as $selected) {
                if ($candidate->course_id === $selected->course_id) {
                    continue;
                }

                foreach ($this->overlapDetails($candidate, $selected) as $overlap) {
                    $conflictsWith[] = [
                        'section_id' => $selected->id,
                        'course_code' => $selected->course->code,
                        'course_name' => $selected->course->name,
                        'group_code' => $selected->group_code,
                        'day' => $overlap['day'],
                        'day_label' => $overlap['day_label'],
                        'starts_at' => $overlap['starts_at'],
                        'ends_at' => $overlap['ends_at'],
                    ];
                }
            }

            if ($conflictsWith !== []) {
                $reasons[] = [
                    'section_id' => $candidate->id,
                    'conflicts_with' => $conflictsWith,
                ];
            }
        }

        return $reasons;
    }

    /**
     * @param  Collection<int, CourseSection>  $selectedSections
     * @param  Collection<int, CourseSection>  $candidateSections
     * @return list<int>
     */
    public function unavailableSectionIds(Collection $selectedSections, Collection $candidateSections): array
    {
        return array_column(
            $this->unavailableSectionReasons($selectedSections, $candidateSections),
            'section_id',
        );
    }

    public function sectionsOverlap(CourseSection $sectionA, CourseSection $sectionB): bool
    {
        return $this->overlapDetails($sectionA, $sectionB) !== [];
    }

    /**
     * @return list<array{day: string, day_label: string, starts_at: string, ends_at: string}>
     */
    private function overlapDetails(CourseSection $sectionA, CourseSection $sectionB): array
    {
        $sectionA->loadMissing('schedules');
        $sectionB->loadMissing('schedules');

        $overlaps = [];

        foreach ($sectionA->schedules as $scheduleA) {
            foreach ($sectionB->schedules as $scheduleB) {
                if ($scheduleA->day !== $scheduleB->day) {
                    continue;
                }

                if (! $this->overlaps($scheduleA->starts_at, $scheduleA->ends_at, $scheduleB->starts_at, $scheduleB->ends_at)) {
                    continue;
                }

                $overlaps[] = [
                    'day' => $scheduleB->day->value,
                    'day_label' => $scheduleB->day->label(),
                    'starts_at' => substr($scheduleB->starts_at, 0, 5),
                    'ends_at' => substr($scheduleB->ends_at, 0, 5),
                ];
            }
        }

        return $overlaps;
    }

    private function overlaps(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }

    private function minTime(string $a, string $b): string
    {
        return min($a, $b);
    }

    private function maxTime(string $a, string $b): string
    {
        return max($a, $b);
    }
}
