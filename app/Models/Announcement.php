<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int|null $learning_path_id
 * @property string $priority
 * @property bool $is_sent_to_email
 * @property Carbon|null $sent_at
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'content',
    'learning_path_id',
    'priority',
    'is_sent_to_email',
    'sent_at',
    'created_by',
])]
class Announcement extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sent_to_email' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the learning path targeted, if any.
     *
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get the user who authored this announcement.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
