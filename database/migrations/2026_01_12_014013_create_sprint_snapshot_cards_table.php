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

        Schema::create('sprint_snapshot_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_snapshot_id')->constrained();
            $table->foreignId('card_id')->constrained();
            $table->string('trello_list_id');
            $table->integer('estimate_points')->nullable();
            $table->boolean('is_done')->default(false);
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
        Schema::dropIfExists('sprint_snapshot_cards');
    }
};
