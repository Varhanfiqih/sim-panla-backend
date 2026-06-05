<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ExcelReportService
{
    public function create(array $headings, iterable $rows): string
    {
        $directory = storage_path('app/report-exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.uniqid('report_', true).'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::BLUE);

        $writer->addRow(Row::fromValues($headings, $headerStyle));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $writer->close();

        return $path;
    }
}
