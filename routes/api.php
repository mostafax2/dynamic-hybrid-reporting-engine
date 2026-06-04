<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mostafax\ReportingEngine\Http\Controllers\ReportController;
use Mostafax\ReportingEngine\Http\Middleware\QueryLimitMiddleware;

/*
|--------------------------------------------------------------------------
| Dynamic Hybrid Reporting Engine — API Routes
|--------------------------------------------------------------------------
|
| Prefix and middleware are configurable in reporting-engine.php.
| The QueryLimitMiddleware applies per-user rate limiting.
|
*/

Route::middleware([QueryLimitMiddleware::class])
    ->group(function () {

        // ── Ad-hoc execution (no saved report) ───────────────────
        Route::post('run', [ReportController::class, 'runAdHoc'])
            ->name('dhr.run-adhoc');

        // ── Saved report CRUD ─────────────────────────────────────
        Route::apiResource('/', ReportController::class)
            ->parameters(['' => 'id'])
            ->names([
                'index'   => 'dhr.index',
                'store'   => 'dhr.store',
                'show'    => 'dhr.show',
                'update'  => 'dhr.update',
                'destroy' => 'dhr.destroy',
            ]);

        // ── Report-specific actions ───────────────────────────────
        Route::post('{id}/run',    [ReportController::class, 'run'])
            ->name('dhr.run');

        Route::get('{id}/export',  [ReportController::class, 'export'])
            ->name('dhr.export');
    });
