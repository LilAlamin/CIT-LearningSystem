<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $path_meeting_id
 * @property int $user_id
 * @property string $status
 * @property int $points
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'path_meeting_id',
    'user_id',
    'status',
    'points',
    'notes',
    'recorded_by',
])]
class MeetingAttendance extends Model
{
    public const STATUS_HADIR = 'hadir';

    public const STATUS_IZIN = 'izin';

    public const STATUS_ALPHA = 'alpha';

    public const POINTS_HADIR = 100;

    public const POINTS_IZIN = 50;

    public const POINTS_ALPHA = 0;

    public const MAX_PATH_POINTS = 600;

    public const MIN_CERTIFICATE_POINTS = 500;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /**
     * Resolve activity points for an attendance status.
     */
    public static function pointsForStatus(string $status): int
    {
        return match ($status) {
            self::STATUS_HADIR => self::POINTS_HADIR,
            self::STATUS_IZIN => self::POINTS_IZIN,
            default => self::POINTS_ALPHA,
        };
    }

    /**
     * Get human-readable label for attendance status.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_HADIR => 'Hadir',
            self::STATUS_IZIN => 'Izin',
            default => 'Tidak Hadir',
        };
    }

    /**
     * Get the meeting of this attendance record.
     *
     * @return BelongsTo<PathMeeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(PathMeeting::class, 'path_meeting_id');
    }

    /**
     * Get the student user of this attendance record.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the staff user (Admin/PJ) who recorded this attendance.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
