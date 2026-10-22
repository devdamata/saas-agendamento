<?php

namespace App\Models;

use App\Enums\CashCategory;
use App\Enums\CashEntryType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\StoresDatesInAppTimezone;
use Database\Factories\CashEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Lançamento do caixa. amount é sempre positivo; type (entrada/saída) vem
 * da categoria quando não é informado.
 */
#[Fillable(['type', 'category', 'amount', 'payment_method', 'description', 'appointment_id', 'occurred_at', 'user_id'])]
class CashEntry extends Model
{
    /** @use HasFactory<CashEntryFactory> */
    use AppendOnly, BelongsToTenant, HasFactory, StoresDatesInAppTimezone;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->type ??= $entry->category->type();

            if ($entry->category->type() !== $entry->type) {
                throw new LogicException("A categoria [{$entry->category->value}] não é do tipo [{$entry->type->value}].");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashEntryType::class,
            'category' => CashCategory::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * Valor com sinal: positivo para entrada, negativo para saída.
     */
    public function signedAmount(): float
    {
        return $this->type === CashEntryType::Income ? (float) $this->amount : -(float) $this->amount;
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
