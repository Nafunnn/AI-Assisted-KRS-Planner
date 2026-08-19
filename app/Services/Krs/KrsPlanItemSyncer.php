<?php

namespace App\Services\Krs;

use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\User;
use Illuminate\Support\Collection;

class KrsPlanItemSyncer
{
    public function __construct(private ScheduleConflictDetector $conflictDetector) {}

    /**
     * @param  list<int>  $sectionIds
     * @return array{applied: bool, section_ids: list<int>, errors: list<string>, plan_summary: array<string, mixed>}
     */
    public function sync(User $user, KrsPlan $plan, array $sectionIds, string $mode = 'replace', bool $apply = false): array
    {
        $plan->load('courseOffering');

        $sections = CourseSection::query()
            ->with(['schedules', 'course'])
            ->whereIn('id', $sectionIds)
            ->get()
            ->filter(fn (CourseSection $section) => $section->course->course_offering_id === $plan->course_offering_id);

        $errors = [];
        $validIds = $this->filterConflictFree($sections, $sectionIds, $errors);

        if (! $apply) {
            return [
                'applied' => false,
                'section_ids' => $validIds,
                'errors' => $errors,
                'plan_summary' => $this->summarize($plan, $validIds),
            ];
        }

        if ($mode === 'replace') {
            $plan->items()->delete();
        }

        foreach ($validIds as $sectionId) {
            $section = $sections->firstWhere('id', $sectionId);

            if (! $section) {
                continue;
            }

            $plan->items()
                ->whereHas('courseSection', fn ($q) => $q->where('course_id', $section->course_id))
                ->delete();

            $plan->items()->create(['course_section_id' => $sectionId]);
        }

        return [
            'applied' => true,
            'section_ids' => $validIds,
            'errors' => $errors,
            'plan_summary' => $this->summarize($plan->fresh(), $validIds),
        ];
    }

    /**
     * @param  Collection<int, CourseSection>  $sections
     * @param  list<int>  $requestedIds
     * @param  list<string>  $errors
     * @return list<int>
     */
    protected function filterConflictFree($sections, array $requestedIds, array &$errors): array
    {
        $validIds = [];

        foreach ($requestedIds as $sectionId) {
            $section = $sections->firstWhere('id', $sectionId);

            if (! $section) {
                $errors[] = "Kelompok {$sectionId} tidak termasuk penawaran ini.";

                continue;
            }

            $replacingId = CourseSection::query()
                ->whereIn('id', $validIds)
                ->where('course_id', $section->course_id)
                ->value('id');

            $selectedSections = $sections->whereIn('id', $validIds)
                ->filter(fn (CourseSection $s) => $replacingId === null || $s->id !== $replacingId);

            foreach ($selectedSections as $other) {
                if ($this->conflictDetector->sectionsOverlap($section, $other)) {
                    $errors[] = "Kelompok {$sectionId} bentrok dengan pilihan lain.";

                    continue 2;
                }
            }

            if ($replacingId !== null) {
                $validIds = array_values(array_filter($validIds, fn ($id) => $id !== $replacingId));
            }

            $validIds[] = $sectionId;
        }

        return $validIds;
    }

    /**
     * @param  list<int>  $sectionIds
     * @return array<string, mixed>
     */
    public function summarize(KrsPlan $plan, array $sectionIds): array
    {
        $sections = CourseSection::query()
            ->with(['schedules', 'course'])
            ->whereIn('id', $sectionIds)
            ->get();

        $conflicts = $this->conflictDetector->detect($sections);

        $totalSks = $sections->unique(fn (CourseSection $s) => $s->course->code)
            ->sum(fn (CourseSection $s) => $s->course->sks);

        $offering = $plan->courseOffering()->with(['courses.sections.schedules', 'courses.sections.course'])->first();
        $allSections = $offering?->courses->flatMap(fn ($c) => $c->sections) ?? collect();
        $unavailable = $this->conflictDetector->unavailableSectionReasons($sections, $allSections);

        return [
            'plan_id' => $plan->id,
            'course_count' => $sections->unique('course_id')->count(),
            'total_sks' => $totalSks,
            'has_conflicts' => $conflicts !== [],
            'conflicts' => $conflicts,
            'unavailable_section_ids' => array_column($unavailable, 'section_id'),
            'unavailable_sections' => $unavailable,
            'section_ids' => $sectionIds,
            'items' => $sections->map(fn (CourseSection $s) => [
                'code' => $s->course->code,
                'name' => $s->course->name,
                'group' => $s->group_code,
                'sks' => $s->course->sks,
                'schedules' => $s->schedules->pluck('raw')->all(),
            ])->values()->all(),
        ];
    }
}
