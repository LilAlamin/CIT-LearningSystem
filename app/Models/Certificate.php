<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $learning_path_id
 * @property string $certificate_number
 * @property string $title
 * @property string $status
 * @property string|null $grade
 * @property string|null $notes
 * @property Carbon $issued_at
 * @property int|null $issued_by
 * @property string|null $certificate_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'learning_path_id',
    'certificate_number',
    'title',
    'status',
    'grade',
    'notes',
    'issued_at',
    'issued_by',
    'certificate_url',
])]
class Certificate extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * Student who owns this certificate.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Learning path of the certificate.
     *
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Admin who issued this certificate.
     *
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Check if certificate is valid and issued.
     */
    public function isValid(): bool
    {
        return $this->status === 'issued';
    }
}
