<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Exporters;

use Mostafax\ReportingEngine\Contracts\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class JsonExporter implements ExporterInterface
{
    public function stream(iterable $data, string $filename): StreamedResponse
    {
        return new StreamedResponse(
            function () use ($data) {
                $out = fopen('php://output', 'wb');
                fwrite($out, '[');
                $first = true;
                foreach ($data as $row) {
                    if (!$first) {
                        fwrite($out, ',');
                    }
                    fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $first = false;
                }
                fwrite($out, ']');
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
        $handle = fopen($path, 'wb');
        fwrite($handle, '[');
        $first = true;
        foreach ($data as $row) {
            if (!$first) {
                fwrite($handle, ',');
            }
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $first = false;
        }
        fwrite($handle, ']');
        fclose($handle);
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    public function extension(): string
    {
        return 'json';
    }
}
