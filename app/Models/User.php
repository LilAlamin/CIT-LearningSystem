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
#[Fillable(['name', 'email', 'password', 'major', 'batch_year', 'role', 'email_verified_at'])]
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
     * Determine if the user is a Penanggung Jawab (PJ) for learning paths.
     */
    public function isPj(): bool
    {
        return $this->role === 'pj';
    }

    /**
     * Determine if the user is staff (Administrator or PJ).
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'pj'], true);
    }

    /**
     * Get all learning paths managed by this PJ.
     *
     * @return BelongsToMany<LearningPath, $this>
     */
    public function managedLearningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_pjs');
    }

    /**
     * Get IDs of learning paths managed by this user.
     * For administrator, returns all path IDs.
     *
     * @return array<int>
     */
    public function managedLearningPathIds(): array
    {
        if ($this->isAdmin()) {
            return LearningPath::pluck('id')->toArray();
        }

        return $this->managedLearningPaths()->pluck('learning_paths.id')->toArray();
    }

    /**
     * Check if user has permission to manage a specific learning path.
     */
    public function canManagePath(int|LearningPath $path): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isPj()) {
            return false;
        }

        $pathId = $path instanceof LearningPath ? $path->id : $path;

        return in_array($pathId, $this->managedLearningPathIds(), true);
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
     * Get meeting attendance records for the user.
     *
     * @return HasMany<MeetingAttendance, $this>
     */
    public function meetingAttendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    /**
     * Get raw (uncapped) attendance points for a learning path.
     */
    public function rawPointsForPath(int $learningPathId): int
    {
        if ($this->relationLoaded('meetingAttendances')) {
            return (int) $this->meetingAttendances
                ->filter(fn (MeetingAttendance $attendance): bool => $attendance->meeting?->learning_path_id === $learningPathId)
                ->sum('points');
        }

        return (int) $this->meetingAttendances()
            ->whereHas('meeting', fn ($query) => $query->where('learning_path_id', $learningPathId))
            ->sum('points');
    }

    /**
     * Get capped activity points for a learning path (maximum 600 points).
     */
    public function pointsForPath(int $learningPathId): int
    {
        return min(MeetingAttendance::MAX_PATH_POINTS, $this->rawPointsForPath($learningPathId));
    }

    /**
     * Check if the user meets the minimum 500 activity points requirement for a certificate.
     */
    public function isEligibleForCertificate(int $learningPathId): bool
    {
        return $this->pointsForPath($learningPathId) >= MeetingAttendance::MIN_CERTIFICATE_POINTS;
    }

    /**
     * Get attendance breakdown and points summary for a learning path.
     *
     * @return array{hadir: int, izin: int, alpha: int, raw_points: int, points: int, max_points: int, min_certificate_points: int, is_eligible: bool}
     */
    public function attendanceSummaryForPath(int $learningPathId): array
    {
        $records = $this->relationLoaded('meetingAttendances')
            ? $this->meetingAttendances->filter(fn (MeetingAttendance $attendance): bool => $attendance->meeting?->learning_path_id === $learningPathId)
            : $this->meetingAttendances()->whereHas('meeting', fn ($query) => $query->where('learning_path_id', $learningPathId))->get();

        $rawPoints = (int) $records->sum('points');
        $points = min(MeetingAttendance::MAX_PATH_POINTS, $rawPoints);

        return [
            'hadir' => $records->where('status', MeetingAttendance::STATUS_HADIR)->count(),
            'izin' => $records->where('status', MeetingAttendance::STATUS_IZIN)->count(),
            'alpha' => $records->where('status', MeetingAttendance::STATUS_ALPHA)->count(),
            'raw_points' => $rawPoints,
            'points' => $points,
            'max_points' => MeetingAttendance::MAX_PATH_POINTS,
            'min_certificate_points' => MeetingAttendance::MIN_CERTIFICATE_POINTS,
            'is_eligible' => $points >= MeetingAttendance::MIN_CERTIFICATE_POINTS,
        ];
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
