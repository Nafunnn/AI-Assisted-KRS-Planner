<?php

namespace App\Models;

use App\Enums\KrsPlanItemStatus;
use Database\Factories\KrsPlanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $krs_plan_id
 * @property int $course_section_id
 * @property KrsPlanItemStatus $status
 * @property string|null $schedule_fingerprint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KrsPlanItem extends Model
{
    /** @use HasFactory<KrsPlanItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'krs_plan_id',
        'course_section_id',
        'status',
        'schedule_fingerprint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KrsPlanItemStatus::class,
        ];
    }

    /**
     * @return BelongsTo<KrsPlan, $this>
     */
    public function krsPlan(): BelongsTo
    {
        return $this->belongsTo(KrsPlan::class);
    }

    /**
     * @return BelongsTo<CourseSection, $this>
     */
    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }
}
