<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'agendado';
    case Confirmed = 'confirmado';
    case Completed = 'concluido';
    case Cancelled = 'cancelado';
    case NoShow = 'faltou';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Confirmed => 'Confirmado',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
            self::NoShow => 'Faltou',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Confirmed => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::NoShow => 'warning',
        };
    }

    /**
     * Status que ocupam o horário do profissional. Deve bater com o WHERE da
     * constraint appointments_no_overlap.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Scheduled, self::Confirmed, self::Completed];
    }

    /**
     * Fluxo: agendado → confirmado → concluído | cancelado | faltou. Um
     * agendamento não confirmado também pode ser concluído, cancelado ou
     * marcado como falta direto. Concluído, cancelado e faltou são finais.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::Completed, self::Cancelled, self::NoShow],
            self::Confirmed => [self::Completed, self::Cancelled, self::NoShow],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
