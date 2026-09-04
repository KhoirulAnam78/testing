<?php

namespace Tests\Unit;

use App\Exports\ArraySheetExport;
use App\Exports\ArrayTemplateExport;
use Maatwebsite\Excel\Excel as FormatExcel;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ExcelTemplateStyleTest extends TestCase
{
    public function test_template_umum_rapi_dan_identifier_panjang_tetap_string(): void
    {
        $workbook = $this->workbook(new ArrayTemplateExport([
            ['nim', 'nip'],
            ['00123456', '198001012006041001'],
        ]));

        try {
            $sheet = $workbook->getActiveSheet();

            $this->assertTrue($sheet->getShowGridlines());
            $this->assertSame('A2', $sheet->getFreezePane());
            $this->assertSame('A1:B2', $sheet->getAutoFilter()->getRange());
            $this->assertSame('1F4E78', $sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            $this->assertSame('00123456', $sheet->getCell('A2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
            $this->assertSame('198001012006041001', $sheet->getCell('B2')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_sheet_nilai_menandai_area_input_dan_membatasi_nilai(): void
    {
        $workbook = $this->workbook(new ArraySheetExport('Nilai', [
            ['nim', 'nama', 'kog'],
            ['00123456', 'Mahasiswa Uji', 7.5],
        ], [
            ['min' => 0, 'maks' => 10, 'nama' => 'Kognitif'],
        ]));

        try {
            $sheet = $workbook->getActiveSheet();
            $validation = $sheet->getDataValidation('C2');

            $this->assertSame('EAF2F8', $sheet->getStyle('A2')->getFill()->getStartColor()->getRGB());
            $this->assertSame('FFF2CC', $sheet->getStyle('C2')->getFill()->getStartColor()->getRGB());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('C2')->getDataType());
            $this->assertSame(7.5, $sheet->getCell('C2')->getValue());
            $this->assertSame(DataValidation::TYPE_DECIMAL, $validation->getType());
            $this->assertSame(DataValidation::OPERATOR_BETWEEN, $validation->getOperator());
            $this->assertSame('0', $validation->getFormula1());
            $this->assertSame('10', $validation->getFormula2());
            $this->assertSame('C2:C2', $validation->getSqref());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_sheet_petunjuk_memisahkan_instruksi_dan_tabel_komponen(): void
    {
        $workbook = $this->workbook(new ArraySheetExport('Petunjuk', [
            ['Petunjuk pengisian nilai pertemuan'],
            [],
            ['1.', 'Isi hanya sheet "Nilai".'],
            ['2.', 'Jangan mengubah judul kolom.'],
            ['3.', 'Kolom nama hanya keterangan.'],
            ['4.', 'Nilai harus sesuai batas.'],
            ['5.', 'Sel kosong menghapus nilai.'],
            ['6.', 'Baris yang dihapus tidak disentuh.'],
            ['7.', 'Satu baris ditolak membatalkan file.'],
            [],
            ['Komponen penilaian kegiatan ini'],
            ['kolom di sheet Nilai', 'kode', 'komponen', 'nilai_min', 'nilai_maks'],
            ['kog', 'KOG', 'Kognitif', 0, 10],
        ]));

        try {
            $sheet = $workbook->getActiveSheet();

            $this->assertTrue($sheet->getShowGridlines());
            $this->assertArrayHasKey('A1:E1', $sheet->getMergeCells());
            $this->assertSame('A13', $sheet->getFreezePane());
            $this->assertSame('A12:E13', $sheet->getAutoFilter()->getRange());
            $this->assertSame('4472C4', $sheet->getStyle('A12')->getFill()->getStartColor()->getRGB());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function workbook(ArrayTemplateExport $export): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'template-excel');
        file_put_contents($path, Excel::raw($export, FormatExcel::XLSX));

        try {
            Cell::setValueBinder(new DefaultValueBinder);

            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }
}
