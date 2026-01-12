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

        Schema::create('daily_sprint_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained();
            $table->date('snapshot_date');
            $table->integer('remaining_points')->default(0);
            $table->integer('completed_points_to_date')->default(0);
            $table->integer('scope_points')->default(0);
            $table->integer('cards_done_count')->default(0);
            $table->integer('cards_total_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sprint_snapshots');
    }
};
