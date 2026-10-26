<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $event_date
 * @property string|null $event_time
 * @property string $event_type
 * @property int|null $learning_path_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'description',
    'event_date',
    'event_time',
    'event_type',
    'learning_path_id',
])]
class CalendarEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    /**
     * Get the learning path associated with the event.
     *
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }
}
