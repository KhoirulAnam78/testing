<?php

namespace App\Exports;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PowerComponents\LivewirePowerGrid\Components\Exports\Contracts\ExportInterface;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PowerGridExportToXLS extends Export implements ExportInterface
{
    /**
     * @throws \Exception
     */
    public function download(Exportable|array $exportOptions): BinaryFileResponse
    {
        $deleteFileAfterSend = boolval(data_get($exportOptions, 'deleteFileAfterSend'));
        $this->striped = strval(data_get($exportOptions, 'striped'));

        /** @var array $columnWidth */
        $columnWidth = data_get($exportOptions, 'columnWidth', []);
        $this->columnWidth = $columnWidth;

        $this->build($exportOptions);

        return response()
            ->download(storage_path($this->fileName.'.xlsx'))
            ->deleteFileAfterSend($deleteFileAfterSend);
    }

    /**
     * @throws WriterNotOpenedException|IOException
     */
    public function build(Exportable|array $exportOptions): void
    {
        $stripTags = boolval(data_get($exportOptions, 'stripTags', false));
        $data = $this->prepare($this->data, $this->columns, $stripTags);

        $tempFolder = storage_path('framework/powergrid-temp');
        File::ensureDirectoryExists($tempFolder);

        $options = new Options(tempFolder: $tempFolder);
        $writer = new Writer($options);

        $writer->openToFile(storage_path($this->fileName.'.xlsx'));

        $style = (new Style)
            ->withFontBold(true)
            ->withFontSize(12)
            ->withShouldWrapText(false)
            ->withBackgroundColor('d0d3d8');

        $writer->addRow(Row::fromValuesWithStyle($data['headers'], $style));

        /**
         * @var int<1, max> $column
         * @var float $width
         */
        foreach ($this->columnWidth as $column => $width) {
            $options->setColumnWidth($width, $column);
        }

        $default = (new Style)->withFontSize(12);
        $gray = (new Style)
            ->withFontSize(12)
            ->withBackgroundColor($this->striped);

        /** @var array<string> $row */
        foreach ($data['rows'] as $key => $row) {
            if (! count($row)) {
                continue;
            }

            $writer->addRow(Row::fromValuesWithStyle(
                $row,
                $key % 2 && $this->striped ? $gray : $default,
            ));
        }

        $writer->close();
    }
}
