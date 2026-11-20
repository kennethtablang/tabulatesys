<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('pageant');          // pageant, singing, dance, talent, debate, other
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->date('event_date')->nullable();
            $table->string('scoring_method', 20)->default('average'); // average | ranking
            $table->string('status', 20)->default('draft');           // draft | ongoing | completed
            $table->timestamps();
        });

        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('weight', 6, 2)->default(100);   // % of round total
            $table->unsignedInteger('sequence')->default(1);
            $table->string('status', 20)->default('pending'); // pending | open | locked
            $table->timestamps();
        });

        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('weight', 6, 2)->default(100);    // % of category score
            $table->decimal('min_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(100);
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();
        });

        Schema::create('contestants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name');
            $table->string('division', 50)->nullable(); // e.g. Mr / Ms, Elementary / High School
            $table->string('representing')->nullable();
            $table->text('details')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('contestant_round', function (Blueprint $table) {
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained()->cascadeOnDelete();
            $table->primary(['round_id', 'contestant_id']);
        });

        Schema::create('event_judge', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'user_id']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->timestamps();
            $table->unique(['criterion_id', 'contestant_id', 'judge_id']);
            $table->index(['category_id', 'judge_id']);
        });

        Schema::create('category_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->unique(['category_id', 'judge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_submissions');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('event_judge');
        Schema::dropIfExists('contestant_round');
        Schema::dropIfExists('contestants');
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('rounds');
        Schema::dropIfExists('events');
    }
};
