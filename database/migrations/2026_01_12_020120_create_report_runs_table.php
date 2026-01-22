<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_definition_id')->constrained('report_definitions');
            $table->foreignId('sprint_id')->nullable()->constrained('sprints');
            $table->string('status');
            $table->json('params');
            $table->json('snapshot_ref')->nullable();
            $table->string('output_format')->nullable();
            $table->string('output_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
