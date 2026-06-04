<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhr_dashboards', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('created_by')->nullable();
            $table->string('name', 255);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhr_dashboards');
    }
};
