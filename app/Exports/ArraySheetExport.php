<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu sheet bernama dari array baris.
 *
 * Profil "Nilai" dan "Petunjuk" hanya mengatur presentasi workbook. Struktur baris tetap
 * sama agar heading import dan kompatibilitas CSV tidak berubah.
 */
class ArraySheetExport extends ArrayTemplateExport implements WithTitle
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, array{min: float|int, maks: float|int, nama: string}>  $validasiNilai
     */
    public function __construct(
        private readonly string $title,
        array $rows,
        private readonly array $validasiNilai = [],
    ) {
        parent::__construct($rows);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): void
    {
        if ($this->title === 'Petunjuk') {
            $this->stylePetunjuk($sheet);

            return;
        }

        $this->styleTable($sheet);

        if ($this->title === 'Nilai') {
            $this->styleNilai($sheet);
        }
    }

    private function styleNilai(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = max(2, count($this->array()));

        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(16);
        $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(32);
        $sheet->getStyle("A2:B{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EAF2F8');

        if ($lastColumn !== 'B') {
            $sheet->getStyle("C2:{$lastColumn}{$lastRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'numberFormat' => [
                    'formatCode' => '0.##',
                ],
            ]);
        }

        foreach ($this->validasiNilai as $index => $batas) {
            $column = Coordinate::stringFromColumnIndex($index + 3);
            $range = "{$column}2:{$column}{$lastRow}";
            $min = (string) (float) $batas['min'];
            $maks = (string) (float) $batas['maks'];
            $nama = $batas['nama'];
            $validation = (new DataValidation)
                ->setType(DataValidation::TYPE_DECIMAL)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setAllowBlank(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setPromptTitle($nama)
                ->setPrompt("Masukkan angka {$min} sampai {$maks}.")
                ->setErrorTitle('Nilai tidak valid')
                ->setError("Nilai harus berupa angka {$min} sampai {$maks}.")
                ->setFormula1($min)
                ->setFormula2($maks)
                ->setSqref($range);

            $sheet->setDataValidation("{$column}2", $validation);
        }
    }

    private function stylePetunjuk(Worksheet $sheet): void
    {
        $lastRow = max(12, count($this->array()));

        $sheet->setShowGridlines(true);
        $sheet->getParent()?->getDefaultStyle()->getFont()->setName('Aptos')->setSize(11);
        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(32);
        $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(18);
        $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(34);
        $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(14);
        $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(14);

        $sheet->mergeCells('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        for ($row = 3; $row <= 9; $row++) {
            $sheet->mergeCells("B{$row}:E{$row}");
            $sheet->getRowDimension($row)->setRowHeight(30);
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $row % 2 === 0 ? 'F4F7FA' : 'FFFFFF'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_HAIR,
                        'color' => ['rgb' => 'D9E2F3'],
                    ],
                ],
            ]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('1F4E78');
        }

        $sheet->mergeCells('A11:E11');
        $sheet->getStyle('A11:E11')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '1F4E78'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9EAF7'],
            ],
        ]);
        $sheet->getRowDimension(11)->setRowHeight(24);
        $sheet->getStyle('A12:E12')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(12)->setRowHeight(30);
        $sheet->freezePane('A13');
        $sheet->setAutoFilter("A12:E{$lastRow}");
        $sheet->getStyle("A12:E{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9E2F3'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        for ($row = 13; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(22);

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:E{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4F7FA');
            }
        }
    }
}
