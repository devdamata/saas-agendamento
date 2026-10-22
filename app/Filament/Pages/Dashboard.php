<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Tela inicial do painel: números do dia e do mês, agenda de hoje e
 * entradas/saídas recentes (widgets em app/Filament/Widgets).
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Início';

    protected static ?string $navigationLabel = 'Início';
}
