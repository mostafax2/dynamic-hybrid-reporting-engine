<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Exporters;

use Mostafax\ReportingEngine\Contracts\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports to XLSX using PhpSpreadsheet.
 *
 * Requires: composer require phpoffice/phpspreadsheet
 *
 * Memory strategy: data is written in chunks to the spreadsheet;
 * for very large datasets (> 50k rows) prefer CsvExporter.
 */
final class ExcelExporter implements ExporterInterface
{
    public function stream(iterable $data, string $filename): StreamedResponse
    {
        $this->assertPhpSpreadsheet();

        return new StreamedResponse(
            function () use ($data) {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet       = $spreadsheet->getActiveSheet();
                $rowIndex    = 1;
                $headers     = null;

                foreach ($data as $row) {
                    if ($headers === null) {
                        $headers  = array_keys($row);
                        $colIndex = 1;
                        foreach ($headers as $header) {
                            $cell = $sheet->getCellByColumnAndRow($colIndex++, $rowIndex);
                            $cell->setValue($header);
                            $cell->getStyle()->getFont()->setBold(true);
                        }
                        $rowIndex++;
                    }

                    $colIndex = 1;
                    foreach (array_values($row) as $value) {
                        $sheet->getCellByColumnAndRow($colIndex++, $rowIndex)->setValue($value);
                    }
                    $rowIndex++;
                }

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type'        => $this->contentType(),
                'Content-Disposition' => "attachment; filename=\"{$filename}.{$this->extension()}\"",
                'Cache-Control'       => 'max-age=0',
            ],
        );
    }

    public function write(iterable $data, string $path): void
    {
        $this->assertPhpSpreadsheet();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $rowIndex    = 1;
        $headers     = null;

        foreach ($data as $row) {
            if ($headers === null) {
                $headers  = array_keys($row);
                $colIndex = 1;
                foreach ($headers as $header) {
                    $cell = $sheet->getCellByColumnAndRow($colIndex++, $rowIndex);
                    $cell->setValue($header);
                    $cell->getStyle()->getFont()->setBold(true);
                }
                $rowIndex++;
            }

            $colIndex = 1;
            foreach (array_values($row) as $value) {
                $sheet->getCellByColumnAndRow($colIndex++, $rowIndex)->setValue($value);
            }
            $rowIndex++;
        }

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    private function assertPhpSpreadsheet(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new \RuntimeException(
                'phpoffice/phpspreadsheet is required for Excel exports. '
                . 'Run: composer require phpoffice/phpspreadsheet'
            );
        }
    }
}
