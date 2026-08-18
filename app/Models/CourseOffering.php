<?php

namespace App\Models;

use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $source_filename
 * @property Carbon $imported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourseOffering extends Model
{
    /** @use HasFactory<CourseOfferingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'source_filename',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
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
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * @return HasMany<KrsPlan, $this>
     */
    public function krsPlans(): HasMany
    {
        return $this->hasMany(KrsPlan::class);
    }

    /**
     * @return HasOne<KrsPlan, $this>
     */
    public function latestPlan(): HasOne
    {
        return $this->hasOne(KrsPlan::class)->latestOfMany();
    }

    public function nextPlanName(): string
    {
        $count = $this->krsPlans()->count();

        return $count === 0 ? 'Rencana KRS' : 'Rencana KRS '.($count + 1);
    }
}
