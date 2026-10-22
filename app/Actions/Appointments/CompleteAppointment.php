<?php

namespace App\Actions\Appointments;

use App\Actions\Stock\RecordStockMovement;
use App\Enums\AppointmentStatus;
use App\Enums\CashCategory;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Appointment;
use App\Models\CashEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conclui o atendimento numa única transação: muda o status, dá baixa no
 * estoque pela ficha técnica do serviço e lança a entrada no caixa com o
 * valor cobrado. É o único caminho para o status "concluído".
 */
class CompleteAppointment
{
    public function __construct(private RecordStockMovement $recordStockMovement) {}

    /**
     * @param  string|int|float|null  $amountCharged  Valor efetivamente cobrado (com desconto, por
     *                                                exemplo); sem valor, usa o preço do agendamento.
     *
     * @throws ValidationException quando o agendamento não pode ser concluído.
     */
    public function handle(
        Appointment $appointment,
        PaymentMethod $paymentMethod,
        string|int|float|null $amountCharged = null,
        ?User $completedBy = null,
    ): Appointment {
        return DB::transaction(function () use ($appointment, $paymentMethod, $amountCharged, $completedBy): Appointment {
            $appointment = Appointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $appointment->transitionTo(AppointmentStatus::Completed, $completedBy);

            if ($amountCharged !== null) {
                $appointment->update(['price' => $amountCharged]);
            }

            $appointment->load(['client', 'service.serviceProducts.product']);
            $service = $appointment->service;

            foreach ($service->serviceProducts as $item) {
                $this->recordStockMovement->handle(
                    $item->product,
                    StockMovementType::Consumption,
                    -1 * (float) $item->quantity,
                    $appointment,
                    "{$service->name} (agendamento #{$appointment->id})",
                    $completedBy,
                );
            }

            if ((float) $appointment->price > 0) {
                CashEntry::create([
                    'category' => CashCategory::Service,
                    'amount' => $appointment->price,
                    'payment_method' => $paymentMethod,
                    'description' => "{$service->name} - {$appointment->client->name}",
                    'appointment_id' => $appointment->getKey(),
                    'occurred_at' => now(),
                    'user_id' => $completedBy?->getKey(),
                ]);
            }

            return $appointment;
        });
    }
}
