<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula os horários em que um serviço pode começar com um profissional
 * em um dia, no fuso do salão.
 *
 * Um início é válido quando o atendimento (duração) cabe em um intervalo da
 * jornada e o período ocupado (duração + buffer) não colide com outro
 * agendamento ativo nem com folga/bloqueio.
 */
class ListAvailableSlots
{
    /**
     * Espaçamento entre os inícios oferecidos, a partir do início de cada intervalo da jornada.
     */
    public const SLOT_INTERVAL_MINUTES = 15;

    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  string  $date  Dia no fuso do salão, no formato Y-m-d.
     * @param  Appointment|null  $ignoring  Agendamento que não conta como ocupado (o que está sendo remarcado).
     * @return list<CarbonImmutable> Inícios disponíveis, no fuso do salão, em ordem.
     */
    public function handle(Professional $professional, Service $service, string $date, ?Appointment $ignoring = null): array
    {
        if (! $professional->is_active || ! $service->is_active || ! $professional->offers($service)) {
            return [];
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->tenantContext->get()->timezone);

        $workingWindows = $professional->workingHours()
            ->where('weekday', $day->dayOfWeek)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (WorkingHour $workingHour): array => [
                $day->setTimeFromTimeString($workingHour->starts_at),
                $day->setTimeFromTimeString($workingHour->ends_at),
            ]);

        if ($workingWindows->isEmpty()) {
            return [];
        }

        $busyPeriods = $this->busyPeriods($professional, $day, $day->addDay(), $ignoring);
        $now = CarbonImmutable::now();
        $slots = [];

        foreach ($workingWindows as [$windowStart, $windowEnd]) {
            for ($start = $windowStart; $start->addMinutes($service->duration_minutes)->lessThanOrEqualTo($windowEnd); $start = $start->addMinutes(self::SLOT_INTERVAL_MINUTES)) {
                $blockedUntil = $start->addMinutes($service->duration_minutes + $service->buffer_minutes);

                if ($start->lessThanOrEqualTo($now) || $this->overlapsAny($busyPeriods, $start, $blockedUntil)) {
                    continue;
                }

                $slots[] = $start;
            }
        }

        return $slots;
    }

    /**
     * Se o serviço pode começar nesse instante com o profissional.
     */
    public function isAvailable(Professional $professional, Service $service, CarbonImmutable $startsAt, ?Appointment $ignoring = null): bool
    {
        $date = $startsAt->setTimezone($this->tenantContext->get()->timezone)->toDateString();

        return collect($this->handle($professional, $service, $date, $ignoring))
            ->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startsAt));
    }

    /**
     * Agendamentos ativos e folgas que tocam o período, como pares [início, fim].
     *
     * @return Collection<int, array{CarbonImmutable, CarbonImmutable}>
     */
    private function busyPeriods(Professional $professional, CarbonImmutable $from, CarbonImmutable $until, ?Appointment $ignoring): Collection
    {
        $appointments = Appointment::occupying()
            ->whereBelongsTo($professional)
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
            ->where('starts_at', '<', $until->utc())
            ->where('blocked_until', '>', $from->utc())
            ->get(['starts_at', 'blocked_until'])
            ->map(fn (Appointment $appointment): array => [$appointment->starts_at, $appointment->blocked_until]);

        $timeOffs = TimeOff::whereBelongsTo($professional)
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->get(['starts_at', 'ends_at'])
            ->map(fn (TimeOff $timeOff): array => [$timeOff->starts_at, $timeOff->ends_at]);

        return $appointments->concat($timeOffs);
    }

    /**
     * @param  Collection<int, array{CarbonImmutable, CarbonImmutable}>  $busyPeriods
     */
    private function overlapsAny(Collection $busyPeriods, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $busyPeriods->contains(
            fn (array $period): bool => $period[0]->lessThan($end) && $period[1]->greaterThan($start)
        );
    }
}
