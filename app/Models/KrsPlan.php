<?php

namespace App\Models;

use App\Enums\KrsPlanStatus;
use Database\Factories\KrsPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $course_offering_id
 * @property string $name
 * @property KrsPlanStatus $status
 * @property bool $is_shared_with_friends
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KrsPlan extends Model
{
    /** @use HasFactory<KrsPlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'course_offering_id',
        'name',
        'status',
        'is_shared_with_friends',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KrsPlanStatus::class,
            'is_shared_with_friends' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return HasMany<KrsPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(KrsPlanItem::class);
    }

    public function totalSks(): int
    {
        return $this->items()
            ->with('courseSection.course')
            ->get()
            ->unique(fn (KrsPlanItem $item) => $item->courseSection->course->code)
            ->sum(fn (KrsPlanItem $item) => $item->courseSection->course->sks);
    }
}
