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

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained();
            $table->foreignId('sprint_id')->nullable()->constrained();
            $table->enum('status', ["queued","running","success","failed"])->default('queued');
            $table->json('params');
            $table->json('snapshot_ref')->nullable();
            $table->string('output_format')->nullable();
            $table->string('output_path')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users');
            $table->foreignId('user_id');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
