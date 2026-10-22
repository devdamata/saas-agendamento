<?php

namespace App\Filament\Resources\CashEntries;

use App\Enums\CashCategory;
use App\Enums\CashEntryType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\CashEntries\Pages\ManageCashEntries;
use App\Filament\Resources\CashEntries\Widgets\CashFlowOverview;
use App\Models\CashEntry;
use App\Tenancy\TenantContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Fluxo de caixa: tudo que entra e tudo que sai. Lançamentos não são
 * editados nem apagados. Entradas de atendimento são criadas ao concluir
 * o atendimento; aqui ficam os lançamentos avulsos.
 */
class CashEntryResource extends Resource
{
    protected static ?string $model = CashEntry::class;

    protected static ?string $slug = 'caixa';

    protected static ?string $modelLabel = 'lançamento';

    protected static ?string $pluralModelLabel = 'lançamentos';

    protected static ?string $navigationLabel = 'Caixa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('type')
                    ->label('Tipo')
                    ->options(CashEntryType::class)
                    ->default(CashEntryType::Expense)
                    ->inline()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('category', null))
                    ->columnSpanFull(),
                Select::make('category')
                    ->label('Categoria')
                    ->options(fn (Get $get): array => self::manualCategories($get('type')))
                    ->required(),
                TextInput::make('amount')
                    ->label('Valor')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->prefix('R$'),
                Select::make('payment_method')
                    ->label('Forma de pagamento')
                    ->options(PaymentMethod::class),
                DateTimePicker::make('occurred_at')
                    ->label('Data')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
                TextInput::make('description')
                    ->label('Descrição')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('category')
                    ->label('Categoria'),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('payment_method')
                    ->label('Forma de pagamento'),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->state(fn (CashEntry $record): float => $record->signedAmount())
                    ->money('BRL', locale: 'pt_BR')
                    ->color(fn (CashEntry $record): string => $record->type->getColor())
                    ->alignEnd(),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')
                            ->label('De')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(fn (): string => self::today()->startOfMonth()->toDateString()),
                        DatePicker::make('until')
                            ->label('Até')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(fn (): string => self::today()->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyPeriod($query, $data['from'] ?? null, $data['until'] ?? null))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['from'] ?? null) ? 'De '.CarbonImmutable::parse($data['from'])->format('d/m/Y') : null,
                        filled($data['until'] ?? null) ? 'Até '.CarbonImmutable::parse($data['until'])->format('d/m/Y') : null,
                    ])),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(CashEntryType::class),
                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(CashCategory::class),
                SelectFilter::make('payment_method')
                    ->label('Forma de pagamento')
                    ->options(PaymentMethod::class),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            CashFlowOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCashEntries::route('/'),
        ];
    }

    /**
     * Período em dias inteiros no fuso do salão.
     *
     * @param  Builder<CashEntry>  $query
     * @return Builder<CashEntry>
     */
    public static function applyPeriod(Builder $query, ?string $from, ?string $until): Builder
    {
        $timezone = app(TenantContext::class)->get()->timezone;

        return $query
            ->when($from, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()))
            ->when($until, fn (Builder $query, string $until) => $query->where('occurred_at', '<', CarbonImmutable::parse($until, $timezone)->addDay()->startOfDay()->utc()));
    }

    /**
     * Categorias para lançamento manual. Atendimentos entram no caixa ao
     * serem concluídos na agenda.
     *
     * @return array<string, string>
     */
    private static function manualCategories(mixed $type): array
    {
        $type = $type instanceof CashEntryType ? $type : CashEntryType::tryFrom((string) $type);

        if ($type === null) {
            return [];
        }

        return collect(CashCategory::optionsFor($type))
            ->except(CashCategory::Service->value)
            ->all();
    }

    private static function today(): CarbonImmutable
    {
        return app(TenantContext::class)->now();
    }
}
