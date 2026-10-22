<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $slug = 'servicos';

    protected static ?string $modelLabel = 'serviço';

    protected static ?string $pluralModelLabel = 'serviços';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('Preço')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('R$'),
                TextInput::make('duration_minutes')
                    ->label('Duração')
                    ->required()
                    ->integer()
                    ->minValue(5)
                    ->maxValue(600)
                    ->suffix('min'),
                TextInput::make('buffer_minutes')
                    ->label('Intervalo depois do atendimento')
                    ->helperText('Tempo de limpeza ou preparo antes do próximo cliente.')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(120)
                    ->default(0)
                    ->suffix('min'),
                Select::make('professionals')
                    ->label('Profissionais que fazem este serviço')
                    ->relationship('professionals', 'name')
                    ->multiple()
                    ->preload()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->helperText('Serviços inativos não aparecem para agendamento.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label('Duração')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('buffer_minutes')
                    ->label('Intervalo')
                    ->suffix(' min'),
                TextColumn::make('price')
                    ->label('Preço')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServices::route('/'),
        ];
    }
}
