<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Designated seat of each judge at an event (1 = first seat). Orders judge columns everywhere.
        Schema::table('event_judge', function (Blueprint $table) {
            $table->unsignedSmallInteger('seat')->nullable();
        });

        // Existing judges: seat them in the current (alphabetical) order.
        $rows = DB::table('event_judge')->join('users', 'users.id', '=', 'event_judge.user_id')
            ->orderBy('event_judge.event_id')->orderBy('users.name')
            ->get(['event_judge.event_id', 'event_judge.user_id']);
        $seat = [];
        foreach ($rows as $row) {
            $seat[$row->event_id] = ($seat[$row->event_id] ?? 0) + 1;
            DB::table('event_judge')->where(['event_id' => $row->event_id, 'user_id' => $row->user_id])->update(['seat' => $seat[$row->event_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('event_judge', function (Blueprint $table) {
            $table->dropColumn('seat');
        });
    }
};
