<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Pagination overrides for saved reports
            'page'                          => ['sometimes', 'integer', 'min:1'],
            'per_page'                      => ['sometimes', 'integer', 'min:1', 'max:500'],

            // Optional ad-hoc DSL (used by POST /run)
            'definition'                    => ['sometimes', 'array'],
            'definition.source'             => ['required_with:definition', 'string', 'in:mysql,mongodb'],
            'definition.table'              => ['required_with:definition', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_.]*$/'],

            // Extra runtime filters to overlay on the saved report definition
            'overrides'                     => ['sometimes', 'array'],
            'overrides.filters'             => ['sometimes', 'array'],
            'overrides.pagination'          => ['sometimes', 'array'],
            'overrides.pagination.page'     => ['sometimes', 'integer', 'min:1'],
            'overrides.pagination.per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function paginationOverrides(): array
    {
        $overrides = [];

        if ($this->has('page') || $this->has('per_page')) {
            $overrides['pagination'] = array_filter([
                'page'     => $this->integer('page') ?: null,
                'per_page' => $this->integer('per_page') ?: null,
            ]);
        }

        return array_merge($overrides, (array) $this->input('overrides', []));
    }
}
