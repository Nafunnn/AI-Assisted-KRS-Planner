<?php

namespace App\Services\Krs;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\KrsPlan;
use Illuminate\Support\Collection;

class KrsScheduleGenerator
{
    public function __construct(
        private ScheduleConflictDetector $conflictDetector,
        private KrsPlanItemSyncer $syncer,
    ) {}

    /**
     * @param  array<string, mixed>  $constraints
     * @return array{section_ids: list<int>, summary: array<string, mixed>, message: string}
     */
    public function generate(KrsPlan $plan, array $constraints = [], bool $apply = false): array
    {
        $plan->load('courseOffering.courses.sections.schedules');

        $minSks = (int) ($constraints['min_sks'] ?? 0);
        $maxSks = (int) ($constraints['max_sks'] ?? 999);
        $freeDays = array_map('strtolower', (array) ($constraints['free_days'] ?? []));
        $maxEndTime = $constraints['max_end_time'] ?? null;
        $mode = $constraints['mode'] ?? 'replace';

        $courses = $plan->courseOffering->courses->sortByDesc('sks');
        $selectedIds = [];
        $totalSks = 0;

        foreach ($courses as $course) {
            if ($totalSks >= $maxSks) {
                break;
            }

            $best = $this->pickBestSection($course, $selectedIds, $freeDays, $maxEndTime);

            if ($best === null) {
                continue;
            }

            $selectedIds[] = $best->id;
            $totalSks += $course->sks;
        }

        if ($totalSks < $minSks) {
            foreach ($courses as $course) {
                if ($totalSks >= $minSks) {
                    break;
                }

                if (collect($selectedIds)->contains(fn ($id) => CourseSection::find($id)?->course_id === $course->id)) {
                    continue;
                }

                $best = $this->pickBestSection($course, $selectedIds, $freeDays, $maxEndTime);

                if ($best !== null) {
                    $selectedIds[] = $best->id;
                    $totalSks += $course->sks;
                }
            }
        }

        $result = $this->syncer->sync(
            $plan->user,
            $plan,
            $selectedIds,
            $mode,
            $apply,
        );

        return [
            'section_ids' => $result['section_ids'],
            'summary' => $result['plan_summary'],
            'errors' => $result['errors'],
            'applied' => $result['applied'],
            'message' => $result['applied']
                ? 'Jadwal diterapkan ke rencana.'
                : 'Preview jadwal otomatis (belum diterapkan).',
        ];
    }

    /**
     * @param  list<int>  $selectedIds
     * @param  list<string>  $freeDays
     */
    protected function pickBestSection(Course $course, array $selectedIds, array $freeDays, ?string $maxEndTime): ?CourseSection
    {
        $selectedSections = CourseSection::query()
            ->with('schedules')
            ->whereIn('id', $selectedIds)
            ->get();

        $candidates = $course->sections->load('schedules')->sortBy('group_code');

        foreach ($candidates as $candidate) {
            if ($this->violatesConstraints($candidate, $freeDays, $maxEndTime)) {
                continue;
            }

            $conflict = false;

            foreach ($selectedSections as $selected) {
                if ($this->conflictDetector->sectionsOverlap($candidate, $selected)) {
                    $conflict = true;
                    break;
                }
            }

            if (! $conflict) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $freeDays
     */
    protected function violatesConstraints(CourseSection $section, array $freeDays, ?string $maxEndTime): bool
    {
        foreach ($section->schedules as $schedule) {
            if (in_array(strtolower($schedule->day->value), $freeDays, true)) {
                return true;
            }

            if ($maxEndTime !== null && $schedule->ends_at > $maxEndTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{suggestions: list<array<string, mixed>>}
     */
    public function suggest(KrsPlan $plan, string $focus = 'optimize', ?int $courseId = null): array
    {
        $plan->load([
            'items.courseSection.schedules',
            'items.courseSection.course',
            'courseOffering.courses.sections.schedules',
        ]);

        $selectedSections = $plan->items->map(fn ($item) => $item->courseSection);
        $conflicts = $this->conflictDetector->detect($selectedSections);
        $suggestions = [];

        $courses = $courseId
            ? $plan->courseOffering->courses->where('id', $courseId)
            : $plan->courseOffering->courses;

        if ($focus === 'conflict' && $conflicts !== []) {
            $conflictSectionIds = collect($conflicts)
                ->flatMap(fn (array $c) => [$c['section_a_id'], $c['section_b_id']])
                ->unique();

            foreach ($conflictSectionIds as $sectionId) {
                $section = $selectedSections->firstWhere('id', $sectionId);

                if (! $section) {
                    continue;
                }

                $section->load(['course.sections.schedules']);

                $alternatives = $this->findAlternatives($section, $selectedSections, $plan);

                foreach ($alternatives as $alt) {
                    $suggestions[] = [
                        'course_id' => $section->course_id,
                        'current_section_id' => $section->id,
                        'recommended_section_id' => $alt->id,
                        'reason' => 'Alternatif kelompok untuk menghindari bentrok jadwal.',
                        'applicable' => true,
                    ];
                }
            }
        } else {
            foreach ($courses as $course) {
                $current = $selectedSections->first(fn ($s) => $s->course_id === $course->id);

                if ($current !== null && $focus === 'fill') {
                    continue;
                }

                $others = $selectedSections->filter(fn ($s) => $current === null || $s->id !== $current->id);
                $best = $this->pickBestSection($course, $others->pluck('id')->all(), [], null);

                if ($best !== null && ($current === null || $best->id !== $current->id)) {
                    $suggestions[] = [
                        'course_id' => $course->id,
                        'current_section_id' => $current?->id,
                        'recommended_section_id' => $best->id,
                        'reason' => $current
                            ? 'Kelompok alternatif yang tidak bentrok.'
                            : 'Rekomendasi kelompok untuk melengkapi rencana.',
                        'applicable' => true,
                    ];
                }
            }
        }

        return ['suggestions' => $suggestions];
    }

    /**
     * @param  Collection<int, CourseSection>  $selectedSections
     * @return list<CourseSection>
     */
    protected function findAlternatives(CourseSection $section, Collection $selectedSections, KrsPlan $plan): array
    {
        $others = $selectedSections->filter(fn (CourseSection $s) => $s->id !== $section->id);
        $alternatives = [];

        foreach ($section->course->sections as $candidate) {
            if ($candidate->id === $section->id) {
                continue;
            }

            $conflict = false;

            foreach ($others as $other) {
                if ($this->conflictDetector->sectionsOverlap($candidate, $other)) {
                    $conflict = true;
                    break;
                }
            }

            if (! $conflict) {
                $alternatives[] = $candidate;
            }
        }

        return $alternatives;
    }
}
