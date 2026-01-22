<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_definition_id')->constrained('report_definitions');
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->string('cron');
            $table->string('timezone');
            $table->json('default_params');
            $table->timestamp('last_ran_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
