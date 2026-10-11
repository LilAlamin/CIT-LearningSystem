<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $major
 * @property string|null $batch_year
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'major', 'batch_year', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user has an admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Determine if the user has a regular user role.
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Get all learning path enrollments for the user.
     *
     * @return HasMany<LearningPathEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(LearningPathEnrollment::class);
    }

    /**
     * Get the latest learning path enrollment for the user.
     *
     * @return HasOne<LearningPathEnrollment, $this>
     */
    public function enrollment(): HasOne
    {
        return $this->hasOne(LearningPathEnrollment::class)->latestOfMany();
    }

    /**
     * Get all learning paths the user is enrolled in.
     *
     * @return BelongsToMany<LearningPath, $this>
     */
    public function enrolledLearningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_enrollments')
            ->withPivot('enrolled_at')
            ->withTimestamps();
    }

    /**
     * Determine if the user is enrolled in a given learning path.
     */
    public function isEnrolledIn(int $pathId): bool
    {
        return $this->enrollments()->where('learning_path_id', $pathId)->exists();
    }

    /**
     * Get the materials completed by the user.
     *
     * @return BelongsToMany<Material, $this>
     */
    public function completedMaterials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'material_user_progress')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    /**
     * Get certificates issued to the user.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Check if user has received a certificate for a learning path.
     */
    public function hasCertificateFor(int $pathId): bool
    {
        return $this->certificates()->where('learning_path_id', $pathId)->where('status', 'issued')->exists();
    }

    /**
     * Get certificate for a specific learning path.
     */
    public function certificateFor(int $pathId): ?Certificate
    {
        return $this->certificates()->where('learning_path_id', $pathId)->where('status', 'issued')->first();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
