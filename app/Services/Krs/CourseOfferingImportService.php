<?php

namespace App\Services\Krs;

use App\Enums\KrsPlanStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseSection;
use App\Models\KrsPlan;
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
    public function import(User $user, UploadedFile $file, ?string $title = null): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) {
            throw new InvalidArgumentException('File Excel kosong.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), $rows[0]);
        $expected = $this->scheduleParser->expectedHeaders();

        if ($headers !== $expected) {
            throw new InvalidArgumentException('Header Excel tidak sesuai template penawaran mata kuliah.');
        }

        $errors = [];
        $coursesCount = 0;
        $sectionsCount = 0;

        return DB::transaction(function () use ($user, $file, $title, $rows, &$errors, &$coursesCount, &$sectionsCount) {
            $offering = CourseOffering::query()->create([
                'user_id' => $user->id,
                'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'source_filename' => $file->getClientOriginalName(),
                'imported_at' => now(),
            ]);

            /** @var array<string, Course> $courseCache */
            $courseCache = [];

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

                    $cacheKey = "{$code}|{$classType->value}";

                    if (! isset($courseCache[$cacheKey])) {
                        $course = Course::query()->create([
                            'course_offering_id' => $offering->id,
                            'code' => $code,
                            'name' => $name,
                            'sks' => $sks,
                            'class_type' => $classType,
                        ]);
                        $courseCache[$cacheKey] = $course;
                        $coursesCount++;
                    } else {
                        $course = $courseCache[$cacheKey];
                    }

                    $section = CourseSection::query()->create([
                        'course_id' => $course->id,
                        'group_code' => $groupCode,
                        'time_period' => $timePeriod,
                    ]);

                    foreach ([1, 2, 3] as $slotNumber) {
                        $rawSchedule = trim((string) ($row[4 + $slotNumber] ?? ''));

                        if ($rawSchedule === '') {
                            continue;
                        }

                        $parsed = $this->scheduleParser->parse($rawSchedule);

                        if ($parsed === null) {
                            continue;
                        }

                        $section->schedules()->create([
                            'slot_number' => $slotNumber,
                            'day' => $parsed['day'],
                            'starts_at' => $parsed['starts_at'],
                            'ends_at' => $parsed['ends_at'],
                            'raw' => $rawSchedule,
                        ]);
                    }

                    $sectionsCount++;
                } catch (InvalidArgumentException $exception) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if ($sectionsCount === 0) {
                throw new InvalidArgumentException('Tidak ada data kelompok yang valid di file Excel.');
            }

            KrsPlan::query()->create([
                'user_id' => $user->id,
                'course_offering_id' => $offering->id,
                'name' => 'Rencana KRS',
                'status' => KrsPlanStatus::Draft,
            ]);

            return [
                'offering' => $offering->loadCount(['courses']),
                'courses_count' => $coursesCount,
                'sections_count' => $sectionsCount,
                'errors' => $errors,
            ];
        });
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
