<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app used to run on UTC; it now uses Asia/Manila (UTC+8). Laravel stores times in the app's
 * time zone, so every time saved before the switch is moved forward 8 hours. Only values up to the
 * current UTC time are shifted, so anything already written in Manila time is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;   // fresh MySQL installs start on Manila time; nothing to convert
        }
        $cutoff = now('UTC')->addMinute()->format('Y-m-d H:i:s');

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, ['migrations', 'sqlite_sequence', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches'], true)) {
                continue;
            }
            foreach (Schema::getColumns($name) as $column) {
                if (! in_array(strtolower($column['type_name']), ['datetime', 'timestamp'], true)) {
                    continue;
                }
                $col = $column['name'];
                DB::statement("UPDATE \"$name\" SET \"$col\" = strftime('%Y-%m-%d %H:%M:%S', \"$col\", '+8 hours') WHERE \"$col\" IS NOT NULL AND \"$col\" <= ?", [$cutoff]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible automatically (new rows are already in Manila time); restore the backup instead.
    }
};
