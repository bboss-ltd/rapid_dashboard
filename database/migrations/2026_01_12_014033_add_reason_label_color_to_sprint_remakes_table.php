<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table): void {
            $table->string('reason_label_color')->nullable()->after('reason_label');
        });
    }

    public function down(): void
    {
        Schema::table('sprint_remakes', function (Blueprint $table): void {
            $table->dropColumn('reason_label_color');
        });
    }
};
