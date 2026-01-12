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

        Schema::create('board_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('trello_board_id')->unique();
            $table->dateTime('last_action_occurred_at')->nullable();
            $table->string('last_action_id')->nullable();
            $table->dateTime('last_polled_at')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_sync_cursors');
    }
};
