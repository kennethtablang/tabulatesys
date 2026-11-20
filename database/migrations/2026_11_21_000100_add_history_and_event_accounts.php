<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tabulator account generated for one event only sees that event.
        // (Accounts are removed together with their event in Event::booted().)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->index()->after('role');
        });

        // Every score a judge enters or changes (rapid keystrokes on one cell are merged).
        Schema::create('score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('old_score', 8, 2)->nullable();
            $table->decimal('new_score', 8, 2)->nullable();
            $table->string('source', 20)->default('autosave');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['event_id', 'created_at']);
            $table->index(['judge_id', 'criterion_id', 'contestant_id']);
        });

        // Who did what: logins, activating events, opening categories, generating accounts...
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 50);
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['event_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('score_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
