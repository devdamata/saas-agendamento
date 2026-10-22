<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\MembershipRole;
use App\Models\Appointment;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria um agendamento no horário pedido, se ele estiver livre.
 *
 * O conflito é evitado em duas camadas: o lock no profissional serializa
 * agendamentos simultâneos dele, e a constraint appointments_no_overlap do
 * banco recusa qualquer sobreposição que escape dessa checagem.
 */
class BookAppointment
{
    /**
     * SQLSTATE de violação de exclusion constraint no Postgres.
     */
    private const EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private ListAvailableSlots $listAvailableSlots,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @throws ValidationException quando o horário não está disponível.
     */
    public function handle(
        Professional $professional,
        Service $service,
        User $client,
        DateTimeInterface $startsAt,
        ?User $bookedBy = null,
    ): Appointment {
        $startsAt = CarbonImmutable::instance($startsAt)->utc();

        return DB::transaction(function () use ($professional, $service, $client, $startsAt, $bookedBy): Appointment {
            $professional = Professional::whereKey($professional->getKey())->lockForUpdate()->first();
            $service = Service::whereKey($service->getKey())->first();

            if ($professional === null || $service === null || ! $this->isAvailable($professional, $service, $startsAt)) {
                throw $this->unavailable();
            }

            try {
                $appointment = Appointment::create([
                    'client_id' => $client->getKey(),
                    'professional_id' => $professional->getKey(),
                    'service_id' => $service->getKey(),
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes($service->duration_minutes),
                    'blocked_until' => $startsAt->addMinutes($service->duration_minutes + $service->buffer_minutes),
                    'status' => AppointmentStatus::Scheduled,
                    'price' => $service->price,
                    'created_by' => $bookedBy?->getKey(),
                ]);
            } catch (QueryException $exception) {
                throw $exception->getCode() === self::EXCLUSION_VIOLATION ? $this->unavailable() : $exception;
            }

            $appointment->events()->create([
                'from_status' => null,
                'to_status' => AppointmentStatus::Scheduled,
                'user_id' => $bookedBy?->getKey(),
            ]);

            Membership::firstOrCreate([
                'user_id' => $client->getKey(),
                'role' => MembershipRole::Client,
            ]);

            return $appointment;
        });
    }

    private function isAvailable(Professional $professional, Service $service, CarbonImmutable $startsAt): bool
    {
        $date = $startsAt->setTimezone($this->tenantContext->get()->timezone)->toDateString();

        return collect($this->listAvailableSlots->handle($professional, $service, $date))
            ->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startsAt));
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages([
            'starts_at' => 'Esse horário não está disponível. Escolha outro horário.',
        ]);
    }
}
