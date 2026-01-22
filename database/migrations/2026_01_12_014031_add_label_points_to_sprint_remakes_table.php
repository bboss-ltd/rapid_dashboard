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
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->integer('estimate_points')->nullable()->after('trello_card_id');
            $table->string('label_name')->nullable()->after('estimate_points');
            $table->integer('label_points')->nullable()->after('label_name');
            $table->dateTime('label_set_at')->nullable()->after('label_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->dropColumn(['estimate_points', 'label_name', 'label_points', 'label_set_at']);
        });
    }
};
