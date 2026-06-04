<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhr_report_executions', function (Blueprint $table) {
            $table->id();
            $table->string('report_id', 36)->index();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('executed_by')->nullable();
            $table->decimal('execution_time_ms', 10, 3)->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedBigInteger('memory_bytes')->default(0);
            $table->boolean('cache_hit')->default(false);
            $table->string('source', 20)->nullable();
            $table->char('query_hash', 32)->nullable()->index();
            $table->enum('status', ['success', 'failed', 'timeout'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at');

            $table->foreign('report_id')
                ->references('id')
                ->on('dhr_reports')
                ->cascadeOnDelete();

            $table->index(['report_id', 'executed_at']);
            $table->index(['tenant_id',  'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhr_report_executions');
    }
};
