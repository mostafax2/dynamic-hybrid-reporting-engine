<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhr_reports', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('created_by')->nullable()->index();
            $table->string('name', 255);
            $table->string('description', 1000)->nullable();
            $table->json('definition');
            $table->json('tags')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_cached')->default(true);
            $table->unsignedSmallInteger('cache_ttl')->default(300);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_public']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhr_reports');
    }
};
