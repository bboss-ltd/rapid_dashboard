<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->string('status')->nullable()->after('name');
            $table->string('remakes_list_id')->nullable()->after('done_list_ids');
            $table->text('sprint_goal')->nullable()->after('remakes_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn(['status', 'remakes_list_id', 'sprint_goal']);
        });
    }
};
