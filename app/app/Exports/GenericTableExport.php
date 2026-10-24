<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GenericTableExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly array $headingsList,
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headingsList;
    }

    public function map($row): array
    {
        return array_values($row);
    }
}
