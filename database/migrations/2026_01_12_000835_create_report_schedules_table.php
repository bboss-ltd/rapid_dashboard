<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained();
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->string('cron');
            $table->string('timezone')->default('UTC');
            $table->json('default_params');
            $table->dateTime('last_ran_at')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
