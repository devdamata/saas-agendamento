<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Actions\Appointments\ListAvailableSlots;
use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Formulário de novo agendamento: serviço → profissional → dia → horário
 * livre. O horário é guardado como instante ISO 8601 (UTC).
 */
class AppointmentForm
{
    public const UNAVAILABLE_MESSAGE = 'Esse horário não está disponível. Escolha outro horário.';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('client_id')
                    ->label('Cliente')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::searchClients($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::salonClients()->find($value)?->name)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefone (WhatsApp)')
                            ->tel()
                            ->required()
                            ->regex('/^[\d\s()+-]{10,20}$/'),
                    ])
                    ->createOptionUsing(fn (array $data): int => self::createClient($data['name'], $data['phone']))
                    ->columnSpanFull(),
                Select::make('service_id')
                    ->label('Serviço')
                    ->required()
                    ->options(fn (): array => Service::active()->orderBy('name')->pluck('name', 'id')->all())
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('professional_id', null);
                        $set('starts_at', null);
                    }),
                Select::make('professional_id')
                    ->label('Profissional')
                    ->required()
                    ->options(fn (Get $get): array => self::professionalsFor($get('service_id')))
                    ->disabled(fn (Get $get): bool => blank($get('service_id')))
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('starts_at', null)),
                DatePicker::make('date')
                    ->label('Dia')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => self::today())
                    ->minDate(fn (): string => self::today())
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('starts_at', null)),
                Select::make('starts_at')
                    ->label('Horário')
                    ->required()
                    ->options(fn (Get $get): array => self::slotOptions($get('professional_id'), $get('service_id'), $get('date')))
                    ->disabled(fn (Get $get): bool => blank($get('professional_id')) || blank($get('date')))
                    ->placeholder('Escolha o serviço, o profissional e o dia')
                    ->validationMessages(['in' => self::UNAVAILABLE_MESSAGE]),
            ]);
    }

    /**
     * Clientes do salão atual (com vínculo de cliente).
     *
     * @return Builder<User>
     */
    private static function salonClients(): Builder
    {
        return User::query()->whereHas('memberships', fn (Builder $query) => $query->where('role', MembershipRole::Client));
    }

    /**
     * @return array<int, string>
     */
    private static function searchClients(string $search): array
    {
        $digits = preg_replace('/\D/', '', $search);

        return self::salonClients()
            ->where(function (Builder $query) use ($search, $digits): void {
                $query->where('name', 'ilike', "%{$search}%");

                if ($digits !== '') {
                    $query->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone'])
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->phone})"])
            ->all();
    }

    /**
     * Cadastra o cliente no salão. Se o telefone já existe (cliente de outro
     * salão), reaproveita o usuário e só cria o vínculo.
     */
    private static function createClient(string $name, string $phone): int
    {
        $user = User::firstOrCreate(['phone' => User::normalizePhone($phone)], ['name' => $name]);

        Membership::firstOrCreate(['user_id' => $user->id, 'role' => MembershipRole::Client]);

        return $user->id;
    }

    /**
     * @return array<int, string>
     */
    private static function professionalsFor(mixed $serviceId): array
    {
        if (blank($serviceId)) {
            return [];
        }

        return Professional::active()
            ->whereHas('services', fn (Builder $query) => $query->whereKey($serviceId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string> instante ISO 8601 (UTC) => "HH:MM" no fuso do salão
     */
    private static function slotOptions(mixed $professionalId, mixed $serviceId, mixed $date): array
    {
        $professional = blank($professionalId) ? null : Professional::find($professionalId);
        $service = blank($serviceId) ? null : Service::find($serviceId);

        if ($professional === null || $service === null || blank($date)) {
            return [];
        }

        return collect(app(ListAvailableSlots::class)->handle($professional, $service, CarbonImmutable::parse($date)->toDateString()))
            ->mapWithKeys(fn (CarbonImmutable $slot): array => [$slot->utc()->toIso8601String() => $slot->format('H:i')])
            ->all();
    }

    private static function today(): string
    {
        return app(TenantContext::class)->now()->toDateString();
    }
}
