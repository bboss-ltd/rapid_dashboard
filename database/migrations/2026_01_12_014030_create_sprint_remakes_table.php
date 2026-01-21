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
        Schema::create('sprint_remakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->string('trello_card_id');
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['sprint_id', 'trello_card_id']);
            $table->index(['sprint_id', 'first_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprint_remakes');
    }
};
