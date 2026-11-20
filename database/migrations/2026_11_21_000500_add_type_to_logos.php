<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the logo is: school, organization, LGU, sponsor...
        Schema::table('logos', function (Blueprint $table) {
            $table->string('type', 20)->default('other')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('logos', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
