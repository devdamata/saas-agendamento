<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'professional', 'service']))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Horário')
                    ->dateTime('d/m H:i')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->description(fn (Appointment $record): string => $record->client->phone)
                    ->searchable(),
                TextColumn::make('service.name')
                    ->label('Serviço'),
                TextColumn::make('professional.name')
                    ->label('Profissional'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('price')
                    ->label('Valor')
                    ->money('BRL', locale: 'pt_BR'),
            ])
            ->filters([
                Filter::make('day')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Dia')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(fn (): string => CarbonImmutable::now(self::timezone())->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['date'] ?? null,
                        function (Builder $query, string $date): void {
                            $day = CarbonImmutable::parse($date, self::timezone())->startOfDay();
                            $query->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $day->addDay()->utc());
                        },
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['date'] ?? null)
                        ? 'Dia '.CarbonImmutable::parse($data['date'])->format('d/m/Y')
                        : null),
                SelectFilter::make('professional_id')
                    ->label('Profissional')
                    ->relationship('professional', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AppointmentStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::statusAction('confirm', 'Confirmar', AppointmentStatus::Confirmed, Heroicon::OutlinedCheck),
                    self::statusAction('complete', 'Concluir', AppointmentStatus::Completed, Heroicon::OutlinedCheckBadge),
                    self::statusAction('noShow', 'Marcar falta', AppointmentStatus::NoShow, Heroicon::OutlinedUserMinus, requiresConfirmation: true),
                    self::statusAction('cancel', 'Cancelar', AppointmentStatus::Cancelled, Heroicon::OutlinedXCircle, requiresConfirmation: true),
                ])->label('Status')->button(),
            ]);
    }

    private static function statusAction(string $name, string $label, AppointmentStatus $status, Heroicon $icon, bool $requiresConfirmation = false): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($status->getColor())
            ->authorize('update')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo($status))
            ->requiresConfirmation($requiresConfirmation)
            ->action(function (Appointment $record) use ($status): void {
                try {
                    app(ChangeAppointmentStatus::class)->handle($record, $status, auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Agendamento '.mb_strtolower($status->label()).'.')->success()->send();
            });
    }

    private static function timezone(): string
    {
        return app(TenantContext::class)->get()->timezone;
    }
}
