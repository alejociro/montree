<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $address
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $notes
 */
final class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'contact_phone',
        'contact_email',
        'notes',
    ];

    public function tourDates(): BelongsToMany
    {
        return $this->belongsToMany(TourDate::class, 'tour_date_hotels');
    }
}
