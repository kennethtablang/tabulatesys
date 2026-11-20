<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // An event can use its own judge score-sheet layout instead of the system default.
        Schema::table('events', function (Blueprint $table) {
            $table->string('sheet_layout', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('sheet_layout');
        });
    }
};
