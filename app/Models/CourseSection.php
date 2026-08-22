<?php

namespace App\Models;

use App\Enums\TimePeriod;
use Database\Factories\CourseSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $course_id
 * @property string $group_code
 * @property TimePeriod $time_period
 * @property Carbon|null $deprecated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourseSection extends Model
{
    /** @use HasFactory<CourseSectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'group_code',
        'time_period',
        'deprecated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_period' => TimePeriod::class,
            'deprecated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<SectionSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(SectionSchedule::class);
    }

    /**
     * @param  Builder<CourseSection>  $query
     * @return Builder<CourseSection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deprecated_at');
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated_at !== null;
    }

    public function scheduleFingerprint(): string
    {
        /** @var Collection<int, SectionSchedule> $schedules */
        $schedules = $this->relationLoaded('schedules')
            ? $this->schedules
            : $this->schedules()->get();

        $parts = $schedules
            ->sortBy(fn (SectionSchedule $schedule) => sprintf(
                '%d-%s-%s-%s',
                $schedule->slot_number,
                $schedule->day->value,
                $schedule->starts_at,
                $schedule->ends_at,
            ))
            ->map(fn (SectionSchedule $schedule) => implode('|', [
                $schedule->slot_number,
                $schedule->day->value,
                substr((string) $schedule->starts_at, 0, 8),
                substr((string) $schedule->ends_at, 0, 8),
            ]))
            ->values()
            ->all();

        return hash('sha256', implode(';', $parts));
    }
}
