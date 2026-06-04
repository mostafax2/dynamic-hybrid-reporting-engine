<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    /**
     * Stream exported data to the client as a file download.
     *
     * @param  iterable<array<string, mixed>>  $data
     */
    public function stream(iterable $data, string $filename): StreamedResponse;

    /**
     * Write data to a local path (used for async/queue-based exports).
     *
     * @param  iterable<array<string, mixed>>  $data
     */
    public function write(iterable $data, string $path): void;

    public function contentType(): string;

    public function extension(): string;
}
