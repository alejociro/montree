<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\DocumentType;
use App\Enums\Eps;
use Database\Factories\BookingTravelerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property string $full_name
 * @property bool $is_minor
 * @property DocumentType|null $document_type
 * @property string|null $document_number
 * @property Carbon|null $birth_date
 * @property string|null $nationality
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $dietary_restrictions
 * @property string|null $medical_notes
 * @property Eps|null $eps
 * @property string|null $eps_other
 * @property string|null $eps_label
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relationship
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
        'is_minor',
        'document_type',
        'document_number',
        'birth_date',
        'nationality',
        'email',
        'phone',
        'dietary_restrictions',
        'medical_notes',
        'eps',
        'eps_other',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'is_minor' => 'boolean',
            'birth_date' => 'date',
            'document_type' => DocumentType::class,
            'eps' => Eps::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Etiqueta del enum, no el texto libre: `eps_other` viaja aparte.
     *
     * @return Attribute<string|null, never>
     */
    protected function epsLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->eps?->label());
    }

    /**
     * Búsqueda de la planilla: nombre o número de documento.
     *
     * @param  Builder<BookingTraveler>  $query
     * @return Builder<BookingTraveler>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('full_name', 'like', $like)
                ->orWhere('document_number', 'like', $like);
        });
    }
}
