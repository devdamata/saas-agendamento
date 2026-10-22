<?php

namespace App\Filament\Resources\CashEntries\Pages;

use App\Filament\Resources\CashEntries\CashEntryResource;
use App\Filament\Resources\CashEntries\Widgets\CashFlowOverview;
use App\Models\CashEntry;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageCashEntries extends ManageRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CashEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            CreateAction::make()
                ->label('Novo lançamento')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CashFlowOverview::class,
        ];
    }

    /**
     * Exporta os lançamentos com os filtros e a ordenação da tabela. Formato
     * para o Excel em português: separador ";", vírgula decimal e BOM UTF-8.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();
        $timezone = app(TenantContext::class)->get()->timezone;

        return response()->streamDownload(function () use ($query, $timezone): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Data', 'Tipo', 'Categoria', 'Descrição', 'Forma de pagamento', 'Valor'], ';', '"', '');

            $query->lazy(500)->each(function (CashEntry $entry) use ($output, $timezone): void {
                fputcsv($output, [
                    $entry->occurred_at->setTimezone($timezone)->format('d/m/Y H:i'),
                    $entry->type->getLabel(),
                    $entry->category->getLabel(),
                    $entry->description,
                    $entry->payment_method?->getLabel(),
                    number_format($entry->signedAmount(), 2, ',', ''),
                ], ';', '"', '');
            });

            fclose($output);
        }, 'caixa-'.now($timezone)->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
