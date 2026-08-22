<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FriendshipStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * @return HasMany<CourseOffering, $this>
     */
    public function uploadedOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<KrsPlan, $this>
     */
    public function krsPlans(): HasMany
    {
        return $this->hasMany(KrsPlan::class);
    }

    /**
     * @return HasMany<AiProviderConfig, $this>
     */
    public function aiProviderConfigs(): HasMany
    {
        return $this->hasMany(AiProviderConfig::class);
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function sentFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'requester_id');
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function receivedFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'addressee_id');
    }

    public function isFriendsWith(User $other): bool
    {
        if ($this->id === $other->id) {
            return false;
        }

        return Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(function ($query) use ($other): void {
                $query->where(function ($inner) use ($other): void {
                    $inner->where('requester_id', $this->id)
                        ->where('addressee_id', $other->id);
                })->orWhere(function ($inner) use ($other): void {
                    $inner->where('requester_id', $other->id)
                        ->where('addressee_id', $this->id);
                });
            })
            ->exists();
    }
}
