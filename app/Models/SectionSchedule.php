<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\SectionScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_section_id
 * @property int $slot_number
 * @property DayOfWeek $day
 * @property string $starts_at
 * @property string $ends_at
 * @property string $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SectionSchedule extends Model
{
    /** @use HasFactory<SectionScheduleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_section_id',
        'slot_number',
        'day',
        'starts_at',
        'ends_at',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => DayOfWeek::class,
        ];
    }

    /**
     * @return BelongsTo<CourseSection, $this>
     */
    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }
}
