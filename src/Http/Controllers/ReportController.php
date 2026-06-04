<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Application\Services\ExportService;
use Mostafax\ReportingEngine\Application\Services\ReportService;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;
use Mostafax\ReportingEngine\Domain\Report\Exceptions\ReportNotFoundException;
use Mostafax\ReportingEngine\Http\Requests\CreateReportRequest;
use Mostafax\ReportingEngine\Http\Requests\RunReportRequest;
use Mostafax\ReportingEngine\Http\Resources\ExecutionResultResource;
use Mostafax\ReportingEngine\Http\Resources\ReportResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService    $reportService,
        private readonly ExecutionService $executionService,
        private readonly ExportService    $exportService,
    ) {}

    // ── GET /reports ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $perPage  = min((int) $request->integer('per_page', 15), 100);

        $paginator = $this->reportService->paginate($perPage, $tenantId);

        return response()->json([
            'data'  => ReportResource::collection($paginator->items()),
            'meta'  => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    // ── POST /reports ────────────────────────────────────────────

    public function store(CreateReportRequest $request): JsonResponse
    {
        try {
            $dto    = $request->toDTO(
                tenantId:  $this->resolveTenantId($request),
                createdBy: (string) $request->user()?->getKey(),
            );
            $report = $this->reportService->create($dto);

            return response()->json(new ReportResource($report), 201);
        } catch (DslValidationException $e) {
            return $this->validationError($e);
        }
    }

    // ── GET /reports/{id} ────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        try {
            $report = $this->reportService->findById($id);
            return response()->json(new ReportResource($report));
        } catch (ReportNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    // ── PUT /reports/{id} ────────────────────────────────────────

    public function update(CreateReportRequest $request, string $id): JsonResponse
    {
        try {
            $dto    = $request->toDTO(
                tenantId:  $this->resolveTenantId($request),
                createdBy: (string) $request->user()?->getKey(),
            );
            $report = $this->reportService->update($id, $dto);

            return response()->json(new ReportResource($report));
        } catch (ReportNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DslValidationException $e) {
            return $this->validationError($e);
        }
    }

    // ── DELETE /reports/{id} ─────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->reportService->delete($id);
            return response()->json(['message' => 'Report deleted.']);
        } catch (ReportNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    // ── POST /reports/{id}/run ───────────────────────────────────

    public function run(RunReportRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->executionService->runById(
                reportId:   $id,
                overrides:  $request->paginationOverrides(),
                userRoles:  (array) ($request->user()?->roles ?? []),
                executedBy: (string) $request->user()?->getKey(),
            );

            return response()->json(new ExecutionResultResource($result));
        } catch (ReportNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DslValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Execution failed.', 'detail' => $e->getMessage()], 500);
        }
    }

    // ── POST /reports/run ─────────────────────────────────────────
    // Ad-hoc DSL execution without saving

    public function runAdHoc(RunReportRequest $request): JsonResponse
    {
        $definition = $request->input('definition');

        if (!$definition) {
            return response()->json(['message' => 'definition is required for ad-hoc execution.'], 422);
        }

        try {
            $result = $this->executionService->runAdHoc(
                rawDsl:    $definition,
                userRoles: (array) ($request->user()?->roles ?? []),
            );

            return response()->json(new ExecutionResultResource($result));
        } catch (DslValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Execution failed.', 'detail' => $e->getMessage()], 500);
        }
    }

    // ── GET /reports/{id}/export ─────────────────────────────────

    public function export(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $format = strtolower($request->query('format', 'csv'));

        try {
            return $this->exportService->exportById(
                reportId:  $id,
                format:    $format,
                userRoles: (array) ($request->user()?->roles ?? []),
            );
        } catch (ReportNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Export failed.'], 500);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function resolveTenantId(Request $request): ?string
    {
        if (!config('reporting-engine.multi_tenancy.enabled', false)) {
            return null;
        }

        $resolver = config('reporting-engine.multi_tenancy.resolver');

        if (is_callable($resolver)) {
            return $resolver($request);
        }

        return $request->header('X-Tenant-Id')
            ?? $request->user()?->tenant_id
            ?? null;
    }

    private function validationError(DslValidationException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'errors'  => $e->errors(),
        ], 422);
    }
}
