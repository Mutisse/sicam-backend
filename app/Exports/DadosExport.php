<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DadosExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private array $linhas,
        private array $cabecalhos,
        private string $titulo = 'Exportação',
    ) {}

    public function array(): array
    {
        return $this->linhas;
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    public function title(): string
    {
        return substr($this->titulo, 0, 30);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '10B981'],
                ],
                'alignment' => ['horizontal' => 'left'],
            ],
        ];
    }
}