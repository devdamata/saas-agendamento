<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AppointmentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do histórico de status. Registros são só inseridos, nunca alterados.
 */
#[Fillable(['appointment_id', 'from_status', 'to_status', 'user_id', 'description'])]
class AppointmentEvent extends Model
{
    /** @use HasFactory<AppointmentEventFactory> */
    use AppendOnly, BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Quem fez a mudança; null quando foi o sistema (ex.: webhook do WhatsApp).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
