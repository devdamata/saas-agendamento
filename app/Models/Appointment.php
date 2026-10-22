<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\StoresDatesInAppTimezone;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

/**
 * starts_at → ends_at é o atendimento; até blocked_until (fim + buffer do
 * serviço) o profissional continua ocupado.
 */
#[Fillable(['client_id', 'professional_id', 'service_id', 'starts_at', 'ends_at', 'blocked_until', 'status', 'price', 'created_by'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory, StoresDatesInAppTimezone;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocked_until' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'price' => 'decimal:2',
        ];
    }

    /**
     * Aplica a transição de status e registra no histórico. Use pelas
     * actions (ChangeAppointmentStatus, CompleteAppointment), que fazem isso
     * numa transação com o agendamento travado.
     *
     * @throws ValidationException quando a transição não é permitida.
     */
    public function transitionTo(AppointmentStatus $status, ?User $changedBy = null): void
    {
        $previousStatus = $this->status;

        if (! $previousStatus->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível mudar um agendamento '.mb_strtolower($previousStatus->label()).' para '.mb_strtolower($status->label()).'.',
            ]);
        }

        $this->update(['status' => $status]);

        $this->events()->create([
            'from_status' => $previousStatus,
            'to_status' => $status,
            'user_id' => $changedBy?->getKey(),
        ]);
    }

    /**
     * Agendamentos que ocupam o horário do profissional.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::occupying());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AppointmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class);
    }

    /**
     * Entrada no caixa gerada ao concluir o atendimento.
     *
     * @return HasOne<CashEntry, $this>
     */
    public function cashEntry(): HasOne
    {
        return $this->hasOne(CashEntry::class);
    }

    /**
     * Baixas de estoque geradas ao concluir o atendimento.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
