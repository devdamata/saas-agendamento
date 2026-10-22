<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agenda de hoje na tela inicial, com as mesmas ações de status da agenda.
 * Agendamentos cancelados ficam de fora.
 */
class TodayAppointments extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Agenda de hoje')
            ->query(fn (): Builder => $this->todayQuery())
            ->defaultSort('starts_at')
            ->paginated(false)
            ->emptyStateHeading('Nenhum atendimento hoje')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Horário')
                    ->dateTime('H:i'),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->description(fn (Appointment $record): string => $record->client->phone),
                TextColumn::make('service.name')
                    ->label('Serviço'),
                TextColumn::make('professional.name')
                    ->label('Profissional'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                Action::make('book')
                    ->label('Novo agendamento')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (): string => AppointmentResource::getUrl('create')),
            ])
            ->recordActions([
                AppointmentsTable::statusActions(),
            ]);
    }

    /**
     * @return Builder<Appointment>
     */
    private function todayQuery(): Builder
    {
        $today = app(TenantContext::class)->now()->startOfDay();

        return Appointment::query()
            ->with(['client', 'professional', 'service'])
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->where('starts_at', '>=', $today->utc())
            ->where('starts_at', '<', $today->addDay()->utc());
    }
}
