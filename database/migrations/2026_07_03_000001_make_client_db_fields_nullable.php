<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // MySQL-specific ALTER syntax (production DB)
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE clients MODIFY db_database VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE clients MODIFY db_username VARCHAR(255) DEFAULT NULL');
            DB::statement('ALTER TABLE clients MODIFY db_password TEXT DEFAULT NULL');
        }
        // SQLite (tests) / other drivers: schema builder handles columns; no-op.
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE clients MODIFY db_database VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE clients MODIFY db_username VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE clients MODIFY db_password TEXT NOT NULL');
        }
    }
};
