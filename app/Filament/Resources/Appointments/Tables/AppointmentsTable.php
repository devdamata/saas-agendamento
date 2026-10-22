<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Actions\Appointments\CompleteAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use App\Models\Professional;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                            ->default(fn (): string => app(TenantContext::class)->now()->toDateString()),
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
                self::statusActions(),
            ]);
    }

    /**
     * Ações do agendamento (confirmar, concluir, remarcar, falta, cancelar),
     * usadas na agenda e na agenda de hoje da tela inicial.
     */
    public static function statusActions(): ActionGroup
    {
        return ActionGroup::make([
            self::statusAction('confirm', 'Confirmar', AppointmentStatus::Confirmed, Heroicon::OutlinedCheck),
            self::completeAction(),
            self::rescheduleAction(),
            self::statusAction('noShow', 'Marcar falta', AppointmentStatus::NoShow, Heroicon::OutlinedUserMinus, requiresConfirmation: true),
            self::statusAction('cancel', 'Cancelar', AppointmentStatus::Cancelled, Heroicon::OutlinedXCircle, requiresConfirmation: true),
        ])->label('Ações')->button();
    }

    /**
     * Remarcar oferece só horários livres (ignorando o horário atual do
     * próprio agendamento); a garantia contra conflito é do RescheduleAppointment.
     */
    private static function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Remarcar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Appointment $record): bool => in_array($record->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true))
            ->modalHeading('Remarcar agendamento')
            ->modalSubmitActionLabel('Remarcar')
            ->fillForm(fn (Appointment $record): array => [
                'professional_id' => $record->professional_id,
                'date' => $record->starts_at->setTimezone(self::timezone())->toDateString(),
            ])
            ->schema(fn (Appointment $record): array => [
                Select::make('professional_id')
                    ->label('Profissional')
                    ->options(AppointmentForm::professionalsFor($record->service_id))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('starts_at', null)),
                DatePicker::make('date')
                    ->label('Dia')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(AppointmentForm::today())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('starts_at', null)),
                Select::make('starts_at')
                    ->label('Novo horário')
                    ->options(fn (Get $get): array => AppointmentForm::slotOptions($get('professional_id'), $record->service_id, $get('date'), ignoring: $record))
                    ->required()
                    ->placeholder('Escolha o profissional e o dia')
                    ->validationMessages(['in' => AppointmentForm::UNAVAILABLE_MESSAGE]),
            ])
            ->action(function (Appointment $record, array $data): void {
                try {
                    app(RescheduleAppointment::class)->handle(
                        $record,
                        CarbonImmutable::parse($data['starts_at']),
                        Professional::find($data['professional_id']),
                        auth()->user(),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Agendamento remarcado.')->success()->send();
            });
    }

    /**
     * Concluir pede o valor cobrado e a forma de pagamento; a baixa de
     * estoque e a entrada no caixa são feitas pelo CompleteAppointment.
     */
    private static function completeAction(): Action
    {
        return Action::make('complete')
            ->label('Concluir')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color(AppointmentStatus::Completed->getColor())
            ->authorize('update')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Completed))
            ->modalHeading('Concluir atendimento')
            ->modalDescription('Os produtos da ficha técnica saem do estoque e o valor entra no caixa.')
            ->modalSubmitActionLabel('Concluir')
            ->fillForm(fn (Appointment $record): array => [
                'amount' => $record->price,
                'payment_method' => PaymentMethod::Pix->value,
            ])
            ->schema([
                TextInput::make('amount')
                    ->label('Valor cobrado')
                    ->helperText('Use 0 para cortesia; nesse caso nada entra no caixa.')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('R$'),
                Select::make('payment_method')
                    ->label('Forma de pagamento')
                    ->options(PaymentMethod::class)
                    ->required(),
            ])
            ->action(function (Appointment $record, array $data): void {
                try {
                    app(CompleteAppointment::class)->handle(
                        $record,
                        $data['payment_method'] instanceof PaymentMethod ? $data['payment_method'] : PaymentMethod::from($data['payment_method']),
                        $data['amount'],
                        auth()->user(),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Atendimento concluído.')->success()->send();
            });
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
