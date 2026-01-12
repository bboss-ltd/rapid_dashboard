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

        Schema::create('trello_actions', function (Blueprint $table) {
            $table->id();
            $table->string('trello_action_id')->unique();
            $table->string('trello_board_id');
            $table->string('trello_card_id')->nullable();
            $table->string('type');
            $table->dateTime('occurred_at');
            $table->json('payload');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trello_actions');
    }
};
