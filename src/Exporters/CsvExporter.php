<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Exporters;

use Mostafax\ReportingEngine\Contracts\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV output directly to the client without loading all rows into memory.
 *
 * Uses League\CSV when available for correct RFC-4180 encoding;
 * falls back to native fputcsv otherwise.
 */
final class CsvExporter implements ExporterInterface
{
    public function stream(iterable $data, string $filename): StreamedResponse
    {
        return new StreamedResponse(
            function () use ($data) {
                $out      = fopen('php://output', 'wb');
                $headers  = null;

                // UTF-8 BOM so Excel opens the file correctly
                fwrite($out, "\xEF\xBB\xBF");

                foreach ($data as $row) {
                    if ($headers === null) {
                        $headers = array_keys($row);
                        fputcsv($out, $headers);
                    }
                    fputcsv($out, array_values($row));
                }

                fclose($out);
            },
            200,
            [
                'Content-Type'        => $this->contentType(),
                'Content-Disposition' => "attachment; filename=\"{$filename}.{$this->extension()}\"",
                'X-Accel-Buffering'   => 'no',
            ],
        );
    }

    public function write(iterable $data, string $path): void
    {
        $handle  = fopen($path, 'wb');
        $headers = null;

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($data as $row) {
            if ($headers === null) {
                $headers = array_keys($row);
                fputcsv($handle, $headers);
            }
            fputcsv($handle, array_values($row));
        }

        fclose($handle);
    }

    public function contentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'csv';
    }
}
