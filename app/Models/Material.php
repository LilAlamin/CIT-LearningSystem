<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learning_path_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string $content
 * @property int $order
 * @property int $read_time_minutes
 * @property string|null $resource_url
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'learning_path_id',
    'path_meeting_id',
    'title',
    'slug',
    'summary',
    'content',
    'order',
    'read_time_minutes',
    'resource_url',
    'is_published',
])]
class Material extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'path_meeting_id' => 'integer',
            'order' => 'integer',
            'read_time_minutes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the learning path for this material.
     *
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get the meeting this material belongs to.
     *
     * @return BelongsTo<PathMeeting, $this>
     */
    public function pathMeeting(): BelongsTo
    {
        return $this->belongsTo(PathMeeting::class);
    }

    /**
     * Users who completed this material.
     *
     * @return BelongsToMany<User, $this>
     */
    public function completedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'material_user_progress')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    /**
     * Determine if a user has completed this material.
     */
    public function isCompletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->completedByUsers()->where('user_id', $user->id)->exists();
    }
}
