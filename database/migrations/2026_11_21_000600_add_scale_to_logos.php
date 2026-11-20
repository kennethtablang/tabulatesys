<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-logo size adjustment (%), for logos with a lot of empty space around them.
        Schema::table('logos', function (Blueprint $table) {
            $table->unsignedSmallInteger('scale')->default(100)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('logos', function (Blueprint $table) {
            $table->dropColumn('scale');
        });
    }
};
