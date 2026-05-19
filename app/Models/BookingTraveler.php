<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\BookingTravelerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property string $full_name
 * @property string|null $document_type
 * @property string|null $document_number
 * @property Carbon|null $birth_date
 * @property string|null $nationality
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $dietary_restrictions
 * @property string|null $medical_notes
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 */
class BookingTraveler extends Model
{
    /** @use HasFactory<BookingTravelerFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'full_name',
        'document_type',
        'document_number',
        'birth_date',
        'nationality',
        'email',
        'phone',
        'dietary_restrictions',
        'medical_notes',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
