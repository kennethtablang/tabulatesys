<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How much of the previous round's final score counts in this round.
        // 0 = everyone starts from zero (default); e.g. 30 = final = 70% this round + 30% previous round.
        Schema::table('rounds', function (Blueprint $table) {
            $table->decimal('carry_over', 5, 2)->default(0)->after('sequence');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('carry_over');
        });
    }
};
