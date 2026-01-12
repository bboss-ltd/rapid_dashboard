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

        Schema::create('sprint_close_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained();
            $table->dateTime('closed_at');
            $table->integer('committed_points')->default(0);
            $table->integer('completed_points')->default(0);
            $table->integer('scope_points')->default(0);
            $table->json('committed_card_ids')->nullable();
            $table->json('completed_card_ids')->nullable();
            $table->json('scope_card_ids')->nullable();
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
        Schema::dropIfExists('sprint_close_snapshots');
    }
};
