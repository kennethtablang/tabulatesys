<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Logo library: school, LGU, sponsors... shown on the judges' screens.
        Schema::create('logos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file');
            $table->boolean('show_on_all')->default(false);   // on every event
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        // Logos picked for a specific event.
        Schema::create('event_logo', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logo_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'logo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logo');
        Schema::dropIfExists('logos');
    }
};
