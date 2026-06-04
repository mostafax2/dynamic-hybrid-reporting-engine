<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhr_widgets', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('dashboard_id', 32)->index();
            $table->string('report_id', 36)->index();
            $table->string('title', 255);
            $table->string('type', 30);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('position_x')->default(0);
            $table->unsignedSmallInteger('position_y')->default(0);
            $table->unsignedSmallInteger('width')->default(6);
            $table->unsignedSmallInteger('height')->default(4);
            $table->timestamps();

            $table->foreign('dashboard_id')
                ->references('id')
                ->on('dhr_dashboards')
                ->cascadeOnDelete();

            $table->foreign('report_id')
                ->references('id')
                ->on('dhr_reports')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhr_widgets');
    }
};
