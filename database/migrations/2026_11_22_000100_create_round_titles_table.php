<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Placement titles: rank 1 = "Ms. Foundation Day 2026", rank 2 = "1st Runner-Up"...
        // division NULL = every division; a division's own title overrides it.
        Schema::create('round_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('division')->nullable();
            $table->string('title');
            $table->timestamps();
            $table->unique(['round_id', 'rank', 'division']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_titles');
    }
};
