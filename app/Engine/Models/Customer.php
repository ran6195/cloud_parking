<?php

namespace App\Engine\Models;

use Database\Engine\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente, unico per email normalizzata (minuscolo, senza spazi esterni).
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $email_normalized
 * @property string $phone
 * @property int|null $legacy_id
 */
#[UseFactory(CustomerFactory::class)]
class Customer extends EngineModel
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'legacy_id'];

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->email = trim($customer->email);
            $customer->email_normalized = self::normalizeEmail($customer->email);
        });
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
