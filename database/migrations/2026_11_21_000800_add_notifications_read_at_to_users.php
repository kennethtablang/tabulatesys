<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notification bell: activity newer than this is "unread" for that admin / tabulator.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('notifications_read_id')->default(0)->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications_read_id');
        });
    }
};
