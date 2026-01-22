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
            $table->string('reason_label')->nullable()->after('label_set_at');
            $table->dateTime('reason_set_at')->nullable()->after('reason_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->dropColumn(['reason_label', 'reason_set_at']);
        });
    }
};
