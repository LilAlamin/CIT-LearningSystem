<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string $icon
 * @property string $color
 * @property int|null $quota
 * @property Carbon|null $registration_start_at
 * @property Carbon|null $registration_end_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'slug', 'description', 'icon', 'color', 'quota', 'registration_start_at', 'registration_end_at', 'is_active'])]
class LearningPath extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_start_at' => 'datetime',
            'registration_end_at' => 'datetime',
            'is_active' => 'boolean',
            'quota' => 'integer',
        ];
    }

    /**
     * Get all materials for this learning path.
     *
     * @return HasMany<Material, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('order');
    }

    /**
     * Get all published materials for this learning path.
     *
     * @return HasMany<Material, $this>
     */
    public function publishedMaterials(): HasMany
    {
        return $this->hasMany(Material::class)->where('is_published', true)->orderBy('order');
    }

    /**
     * Get all enrollments for this learning path.
     *
     * @return HasMany<LearningPathEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(LearningPathEnrollment::class);
    }

    /**
     * Get all scheduled meetings for this learning path.
     *
     * @return HasMany<PathMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(PathMeeting::class)->orderBy('meeting_number');
    }

    /**
     * Get next upcoming meeting for this path.
     */
    public function nextMeeting(): ?PathMeeting
    {
        return $this->meetings()->where('meeting_date', '>=', now())->orderBy('meeting_date')->first();
    }

    /**
     * Get all users enrolled in this learning path.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_path_enrollments')
            ->withPivot('enrolled_at')
            ->withTimestamps();
    }

    /**
     * Get announcements for this learning path.
     *
     * @return HasMany<Announcement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /**
     * Get calendar events for this learning path.
     *
     * @return HasMany<CalendarEvent, $this>
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * Get certificates issued for this learning path.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Check if registration is currently open.
     */
    public function isOpenForRegistration(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->registration_start_at && $now->lt($this->registration_start_at)) {
            return false;
        }

        if ($this->registration_end_at && $now->gt($this->registration_end_at)) {
            return false;
        }

        if ($this->isFull()) {
            return false;
        }

        return true;
    }

    /**
     * Check if registration has expired.
     */
    public function isRegistrationExpired(): bool
    {
        return $this->registration_end_at !== null && now()->gt($this->registration_end_at);
    }

    /**
     * Check if registration is not yet open.
     */
    public function isRegistrationUpcoming(): bool
    {
        return $this->registration_start_at !== null && now()->lt($this->registration_start_at);
    }

    /**
     * Check if the quota is full.
     */
    public function isFull(): bool
    {
        if ($this->quota === null) {
            return false;
        }

        return $this->enrollments()->count() >= $this->quota;
    }

    /**
     * Get the remaining quota seats.
     */
    public function remainingQuota(): ?int
    {
        if ($this->quota === null) {
            return null;
        }

        $count = $this->enrollments()->count();

        return max(0, $this->quota - $count);
    }
}
