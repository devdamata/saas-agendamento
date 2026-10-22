<?php

namespace App\Filament\Resources\Professionals\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimeOffsRelationManager extends RelationManager
{
    protected static string $relationship = 'timeOffs';

    protected static ?string $title = 'Folgas e bloqueios';

    protected static ?string $modelLabel = 'folga ou bloqueio';

    protected static ?string $pluralModelLabel = 'folgas e bloqueios';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('starts_at')
                    ->label('Início')
                    ->seconds(false)
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label('Fim')
                    ->seconds(false)
                    ->required()
                    ->after('starts_at'),
                TextInput::make('reason')
                    ->label('Motivo')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('reason')
                    ->label('Motivo'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
