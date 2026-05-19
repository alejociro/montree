<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property int $user_id
 * @property int $booking_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $comment
 * @property ReviewStatus $status
 * @property string|null $admin_response
 * @property int|null $responded_by
 * @property Carbon|null $responded_at
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'tour_id',
        'user_id',
        'booking_id',
        'rating',
        'title',
        'comment',
        'status',
        'admin_response',
        'responded_by',
        'responded_at',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'responded_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
