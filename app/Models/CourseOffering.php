<?php

namespace App\Models;

use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $uploaded_by_user_id
 * @property string $title
 * @property string $term
 * @property string $source_filename
 * @property int $catalog_version
 * @property Carbon $imported_at
 * @property Carbon|null $published_at
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
        'uploaded_by_user_id',
        'title',
        'term',
        'source_filename',
        'catalog_version',
        'imported_at',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'published_at' => 'datetime',
            'catalog_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
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

    /**
     * @param  Builder<CourseOffering>  $query
     * @return Builder<CourseOffering>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function nextPlanNameFor(User $user): string
    {
        $count = $this->krsPlans()->where('user_id', $user->id)->count();

        return $count === 0 ? 'Rencana KRS' : 'Rencana KRS '.($count + 1);
    }
}
