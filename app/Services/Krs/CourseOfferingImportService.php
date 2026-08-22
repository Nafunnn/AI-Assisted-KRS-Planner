<?php

namespace App\Services\Krs;

use App\Enums\ClassType;
use App\Enums\KrsPlanItemStatus;
use App\Enums\TimePeriod;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlanItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CourseOfferingImportService
{
    public function __construct(private ScheduleParser $scheduleParser) {}

    /**
     * @return array{
     *     offering: CourseOffering,
     *     courses_count: int,
     *     sections_count: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function create(User $admin, UploadedFile $file, ?string $title = null, ?string $term = null): array
    {
        $parsed = $this->parseFile($file);

        if ($parsed['rows'] === []) {
            throw new InvalidArgumentException('Tidak ada data kelompok yang valid di file Excel.');
        }

        return DB::transaction(function () use ($admin, $file, $title, $term, $parsed) {
            $offering = CourseOffering::query()->create([
                'uploaded_by_user_id' => $admin->id,
                'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'term' => $term ?: 'default',
                'source_filename' => $file->getClientOriginalName(),
                'catalog_version' => 1,
                'imported_at' => now(),
                'published_at' => now(),
            ]);

            $counts = $this->applyRows($offering, $parsed['rows']);

            return [
                'offering' => $offering->fresh()->loadCount(['courses']),
                'courses_count' => $counts['courses_created'] + $counts['courses_updated'],
                'sections_count' => $counts['sections_created'] + $counts['sections_updated'],
                'errors' => $parsed['errors'],
            ];
        });
    }

    /**
     * @return array{
     *     offering: CourseOffering,
     *     dry_run: bool,
     *     courses_created: int,
     *     courses_updated: int,
     *     sections_created: int,
     *     sections_updated: int,
     *     sections_deprecated: int,
     *     schedule_changed_sections: int,
     *     affected_plan_items: int,
     *     affected_plans_count: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function sync(User $admin, CourseOffering $offering, UploadedFile $file, bool $dryRun = false): array
    {
        $parsed = $this->parseFile($file);

        if ($parsed['rows'] === []) {
            throw new InvalidArgumentException('Tidak ada data kelompok yang valid di file Excel.');
        }

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $counts = $this->applyRows($offering->fresh(), $parsed['rows']);
                $result = $this->buildSyncResult($offering, $admin, $file, $counts, $parsed['errors'], dryRun: true);
            } finally {
                DB::rollBack();
            }

            return $result;
        }

        return DB::transaction(function () use ($admin, $offering, $file, $parsed) {
            $counts = $this->applyRows($offering, $parsed['rows']);

            return $this->buildSyncResult($offering, $admin, $file, $counts, $parsed['errors'], dryRun: false);
        });
    }

    /**
     * @return array{
     *     rows: list<array{
     *         code: string,
     *         name: string,
     *         sks: int,
     *         class_type: ClassType,
     *         group_code: string,
     *         time_period: TimePeriod,
     *         schedules: list<array{slot_number: int, day: mixed, starts_at: string, ends_at: string, raw: string}>
     *     }>,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    private function parseFile(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) {
            throw new InvalidArgumentException('File Excel kosong.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), $rows[0]);

        if ($headers !== $this->scheduleParser->expectedHeaders()) {
            throw new InvalidArgumentException('Header Excel tidak sesuai template penawaran mata kuliah.');
        }

        $errors = [];
        $parsedRows = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                $code = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                $sks = (int) ($row[2] ?? 0);
                $classType = $this->scheduleParser->parseClassType((string) ($row[3] ?? ''));
                $groupCode = trim((string) ($row[4] ?? ''));
                $timePeriod = $this->scheduleParser->parseTimePeriod((string) ($row[8] ?? ''));

                if ($code === '' || $name === '' || $groupCode === '' || $sks <= 0) {
                    throw new InvalidArgumentException('Data baris tidak lengkap.');
                }

                $schedules = [];

                foreach ([1, 2, 3] as $slotNumber) {
                    $rawSchedule = trim((string) ($row[4 + $slotNumber] ?? ''));

                    if ($rawSchedule === '') {
                        continue;
                    }

                    $parsed = $this->scheduleParser->parse($rawSchedule);

                    if ($parsed === null) {
                        continue;
                    }

                    $schedules[] = [
                        'slot_number' => $slotNumber,
                        'day' => $parsed['day'],
                        'starts_at' => $parsed['starts_at'],
                        'ends_at' => $parsed['ends_at'],
                        'raw' => $rawSchedule,
                    ];
                }

                $parsedRows[] = [
                    'code' => $code,
                    'name' => $name,
                    'sks' => $sks,
                    'class_type' => $classType,
                    'group_code' => $groupCode,
                    'time_period' => $timePeriod,
                    'schedules' => $schedules,
                ];
            } catch (InvalidArgumentException $exception) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'rows' => $parsedRows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array{
     *     code: string,
     *     name: string,
     *     sks: int,
     *     class_type: ClassType,
     *     group_code: string,
     *     time_period: TimePeriod,
     *     schedules: list<array{slot_number: int, day: mixed, starts_at: string, ends_at: string, raw: string}>
     * }>  $rows
     * @return array{
     *     courses_created: int,
     *     courses_updated: int,
     *     sections_created: int,
     *     sections_updated: int,
     *     sections_deprecated: int,
     *     schedule_changed_sections: int,
     *     affected_plan_items: int,
     *     affected_plan_ids: list<int>
     * }
     */
    private function applyRows(CourseOffering $offering, array $rows): array
    {
        $coursesCreated = 0;
        $coursesUpdated = 0;
        $sectionsCreated = 0;
        $sectionsUpdated = 0;
        $scheduleChangedSections = 0;
        $affectedPlanItems = 0;
        /** @var array<int, true> $affectedPlanIds */
        $affectedPlanIds = [];
        /** @var array<int, true> $seenSectionIds */
        $seenSectionIds = [];
        /** @var array<string, Course> $courseCache */
        $courseCache = [];

        $offering->load(['courses.sections.schedules']);

        foreach ($offering->courses as $course) {
            $courseCache["{$course->code}|{$course->class_type->value}"] = $course;
        }

        foreach ($rows as $row) {
            $cacheKey = "{$row['code']}|{$row['class_type']->value}";

            if (! isset($courseCache[$cacheKey])) {
                $course = Course::query()->create([
                    'course_offering_id' => $offering->id,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'sks' => $row['sks'],
                    'class_type' => $row['class_type'],
                ]);
                $course->setRelation('sections', collect());
                $courseCache[$cacheKey] = $course;
                $coursesCreated++;
            } else {
                $course = $courseCache[$cacheKey];
                $course->fill([
                    'name' => $row['name'],
                    'sks' => $row['sks'],
                ]);

                if ($course->isDirty()) {
                    $course->save();
                    $coursesUpdated++;
                }
            }

            /** @var CourseSection|null $section */
            $section = $course->sections->firstWhere('group_code', $row['group_code']);
            $isNewSection = $section === null;

            if ($isNewSection) {
                $section = CourseSection::query()->create([
                    'course_id' => $course->id,
                    'group_code' => $row['group_code'],
                    'time_period' => $row['time_period'],
                    'deprecated_at' => null,
                ]);
                $section->setRelation('schedules', collect());
                $course->setRelation('sections', $course->sections->push($section));
                $sectionsCreated++;
            } else {
                $section->fill([
                    'time_period' => $row['time_period'],
                    'deprecated_at' => null,
                ]);

                if ($section->isDirty()) {
                    $section->save();
                }

                $sectionsUpdated++;
            }

            $seenSectionIds[$section->id] = true;
            $oldFingerprint = $isNewSection ? null : $section->scheduleFingerprint();
            $this->replaceSchedules($section, $row['schedules']);
            $section->load('schedules');
            $newFingerprint = $section->scheduleFingerprint();

            if (! $isNewSection && $oldFingerprint !== $newFingerprint) {
                $scheduleChangedSections++;

                $items = KrsPlanItem::query()
                    ->where('course_section_id', $section->id)
                    ->where('status', '!=', KrsPlanItemStatus::SectionRemoved)
                    ->get();

                foreach ($items as $item) {
                    $item->update([
                        'status' => KrsPlanItemStatus::ScheduleChanged,
                        'schedule_fingerprint' => $newFingerprint,
                    ]);
                    $affectedPlanItems++;
                    $affectedPlanIds[$item->krs_plan_id] = true;
                }
            }
        }

        $sectionsDeprecated = 0;

        foreach ($courseCache as $course) {
            foreach ($course->sections as $section) {
                if (isset($seenSectionIds[$section->id]) || $section->deprecated_at !== null) {
                    continue;
                }

                $section->update(['deprecated_at' => now()]);
                $sectionsDeprecated++;

                $items = KrsPlanItem::query()
                    ->where('course_section_id', $section->id)
                    ->where('status', '!=', KrsPlanItemStatus::SectionRemoved)
                    ->get();

                foreach ($items as $item) {
                    $item->update(['status' => KrsPlanItemStatus::SectionRemoved]);
                    $affectedPlanItems++;
                    $affectedPlanIds[$item->krs_plan_id] = true;
                }
            }
        }

        return [
            'courses_created' => $coursesCreated,
            'courses_updated' => $coursesUpdated,
            'sections_created' => $sectionsCreated,
            'sections_updated' => $sectionsUpdated,
            'sections_deprecated' => $sectionsDeprecated,
            'schedule_changed_sections' => $scheduleChangedSections,
            'affected_plan_items' => $affectedPlanItems,
            'affected_plan_ids' => array_map('intval', array_keys($affectedPlanIds)),
        ];
    }

    /**
     * @param  list<array{slot_number: int, day: mixed, starts_at: string, ends_at: string, raw: string}>  $schedules
     */
    private function replaceSchedules(CourseSection $section, array $schedules): void
    {
        $section->schedules()->delete();

        foreach ($schedules as $schedule) {
            $section->schedules()->create([
                'slot_number' => $schedule['slot_number'],
                'day' => $schedule['day'],
                'starts_at' => $schedule['starts_at'],
                'ends_at' => $schedule['ends_at'],
                'raw' => $schedule['raw'],
            ]);
        }
    }

    /**
     * @param  array{
     *     courses_created: int,
     *     courses_updated: int,
     *     sections_created: int,
     *     sections_updated: int,
     *     sections_deprecated: int,
     *     schedule_changed_sections: int,
     *     affected_plan_items: int,
     *     affected_plan_ids: list<int>
     * }  $counts
     * @param  list<array{row: int, message: string}>  $errors
     * @return array{
     *     offering: CourseOffering,
     *     dry_run: bool,
     *     courses_created: int,
     *     courses_updated: int,
     *     sections_created: int,
     *     sections_updated: int,
     *     sections_deprecated: int,
     *     schedule_changed_sections: int,
     *     affected_plan_items: int,
     *     affected_plans_count: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    private function buildSyncResult(
        CourseOffering $offering,
        User $admin,
        UploadedFile $file,
        array $counts,
        array $errors,
        bool $dryRun,
    ): array {
        if (! $dryRun) {
            $offering->update([
                'uploaded_by_user_id' => $admin->id,
                'source_filename' => $file->getClientOriginalName(),
                'catalog_version' => $offering->catalog_version + 1,
                'imported_at' => now(),
            ]);
        }

        return [
            'offering' => $dryRun ? $offering : $offering->fresh()->loadCount(['courses']),
            'dry_run' => $dryRun,
            'courses_created' => $counts['courses_created'],
            'courses_updated' => $counts['courses_updated'],
            'sections_created' => $counts['sections_created'],
            'sections_updated' => $counts['sections_updated'],
            'sections_deprecated' => $counts['sections_deprecated'],
            'schedule_changed_sections' => $counts['schedule_changed_sections'],
            'affected_plan_items' => $counts['affected_plan_items'],
            'affected_plans_count' => count($counts['affected_plan_ids']),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
