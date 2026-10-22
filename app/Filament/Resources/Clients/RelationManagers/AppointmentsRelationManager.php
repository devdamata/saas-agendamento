<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Histórico de atendimentos do cliente neste salão, só leitura.
 */
class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Histórico de atendimentos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['service', 'professional']))
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('Nenhum atendimento ainda')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
            ]);
    }
}
