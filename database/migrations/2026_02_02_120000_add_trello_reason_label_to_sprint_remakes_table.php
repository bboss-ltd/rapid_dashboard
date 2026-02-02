<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->string('trello_reason_label')->nullable()->after('reason_label');
            $table->timestamp('trello_reason_set_at')->nullable()->after('trello_reason_label');
        });
    }

    public function down(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->dropColumn(['trello_reason_label', 'trello_reason_set_at']);
        });
    }
};
