<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->string('production_line')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table) {
            $table->dropIndex(['production_line']);
            $table->dropColumn('production_line');
        });
    }
};
