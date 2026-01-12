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
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trello_board_id')->unique();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('closed_at')->nullable();
            $table->json('done_list_ids')->nullable();
            $table->string('trello_control_card_id')->nullable();
            $table->string('trello_status_custom_field_id')->nullable();
            $table->string('trello_closed_option_id')->nullable();
            $table->dateTime('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
