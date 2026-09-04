<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArrayTemplateExport extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithStyles
{
    public function __construct(private readonly array $rows)
    {
        // String tetap string agar NIM, NIP, kode, dan nomor HP tidak kehilangan nol awal
        // atau presisi. Nilai yang memang dikirim sebagai int/float tetap numerik.
        $this->setNullConversion(false)->setNumericConversion(false);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_int($value) || is_float($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): void
    {
        $this->styleTable($sheet);
    }

    protected function styleTable(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = max(1, count($this->rows));
        $range = "A1:{$lastColumn}{$lastRow}";

        $sheet->setShowGridlines(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($range);
        $sheet->getParent()?->getDefaultStyle()->getFont()->setName('Aptos')->setSize(11);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9E2F3'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(22);

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4F7FA');
            }
        }
    }
}
