<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Exporters;

use Mostafax\ReportingEngine\Contracts\ExporterInterface;

final class ExporterFactory
{
    /** @var array<string, ExporterInterface> */
    private array $exporters;

    public function __construct(
        CsvExporter   $csv,
        JsonExporter  $json,
        ExcelExporter $excel,
    ) {
        $this->exporters = [
            'csv'  => $csv,
            'json' => $json,
            'xlsx' => $excel,
            'xls'  => $excel,
        ];
    }

    /** @throws \InvalidArgumentException */
    public function make(string $format): ExporterInterface
    {
        $format = strtolower($format);

        if (!isset($this->exporters[$format])) {
            throw new \InvalidArgumentException(
                "Unsupported export format '{$format}'. Supported: " . implode(', ', array_keys($this->exporters))
            );
        }

        return $this->exporters[$format];
    }

    public function register(string $format, ExporterInterface $exporter): void
    {
        $this->exporters[strtolower($format)] = $exporter;
    }

    /** @return string[] */
    public function supported(): array
    {
        return array_keys($this->exporters);
    }
}
