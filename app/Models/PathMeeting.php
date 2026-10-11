<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learning_path_id
 * @property int $meeting_number
 * @property string $title
 * @property string|null $description
 * @property Carbon $meeting_date
 * @property string|null $end_time
 * @property string|null $location
 * @property string|null $meeting_url
 * @property string|null $notes
 * @property string|null $recap_summary
 * @property string|null $recap_slides_url
 * @property string|null $recap_recording_url
 * @property bool $is_completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'learning_path_id',
    'meeting_number',
    'title',
    'description',
    'meeting_date',
    'end_time',
    'location',
    'meeting_url',
    'notes',
    'recap_summary',
    'recap_slides_url',
    'recap_recording_url',
    'is_completed',
])]
class PathMeeting extends Model
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
            'meeting_date' => 'datetime',
            'is_completed' => 'boolean',
            'meeting_number' => 'integer',
        ];
    }

    /**
     * Get the learning path of this meeting.
     *
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get materials tied to this meeting.
     *
     * @return HasMany<Material, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Check if the meeting has passed.
     */
    public function isPast(): bool
    {
        if ($this->is_completed) {
            return true;
        }

        return $this->meeting_date !== null && now()->gt($this->meeting_date);
    }
}
