<?php

namespace App\Livewire\Admin\Reports\Concerns;

use App\Exports\GenericTableExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Classes using this trait must implement:
 *   - reportTitle(): string
 *   - headings(): array
 *   - rows(): \Illuminate\Support\Collection
 */
trait ExportsTable
{
    public function exportExcel()
    {
        return (new GenericTableExport($this->headings(), $this->rows()))
            ->download(Str::slug($this->reportTitle()).'.xlsx');
    }

    public function exportPdf()
    {
        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $this->reportTitle(),
            'headings' => $this->headings(),
            'rows' => $this->rows(),
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            Str::slug($this->reportTitle()).'.pdf'
        );
    }
}
