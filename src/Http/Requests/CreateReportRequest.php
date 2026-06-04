<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Mostafax\ReportingEngine\Application\DTO\CreateReportDTO;

final class CreateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization delegated to middleware / policies
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'definition'            => ['required', 'array'],
            'definition.source'     => ['required', 'string', 'in:mysql,mongodb'],
            'definition.table'      => ['required', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_.]*$/'],
            'is_public'             => ['sometimes', 'boolean'],
            'tags'                  => ['sometimes', 'array'],
            'tags.*'                => ['string', 'max:50'],
            'is_cached'             => ['sometimes', 'boolean'],
            'cache_ttl'             => ['sometimes', 'integer', 'min:0', 'max:86400'],
        ];
    }

    public function toDTO(?string $tenantId = null, ?string $createdBy = null): CreateReportDTO
    {
        return CreateReportDTO::fromArray(array_merge($this->validated(), [
            'tenant_id'  => $tenantId,
            'created_by' => $createdBy,
        ]));
    }
}
