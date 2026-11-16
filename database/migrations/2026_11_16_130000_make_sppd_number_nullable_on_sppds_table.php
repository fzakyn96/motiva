<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'sppds_sppd_number_unique' AND object_id = OBJECT_ID('sppds')) DROP INDEX sppds_sppd_number_unique ON sppds");
            DB::statement('ALTER TABLE sppds ALTER COLUMN sppd_number NVARCHAR(255) NULL');
            DB::statement('CREATE UNIQUE INDEX sppds_sppd_number_unique ON sppds (sppd_number) WHERE sppd_number IS NOT NULL');
        } else {
            Schema::table('sppds', function (Blueprint $table) {
                $table->dropUnique(['sppd_number']);
                $table->string('sppd_number')->nullable()->change();
                $table->unique('sppd_number');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'sppds_sppd_number_unique' AND object_id = OBJECT_ID('sppds')) DROP INDEX sppds_sppd_number_unique ON sppds");
            DB::statement("UPDATE sppds SET sppd_number = CONCAT('SPPD-', id) WHERE sppd_number IS NULL");
            DB::statement('ALTER TABLE sppds ALTER COLUMN sppd_number NVARCHAR(255) NOT NULL');
            DB::statement('CREATE UNIQUE INDEX sppds_sppd_number_unique ON sppds (sppd_number)');
        } else {
            Schema::table('sppds', function (Blueprint $table) {
                $table->dropUnique(['sppd_number']);
                $table->string('sppd_number')->nullable(false)->change();
                $table->unique('sppd_number');
            });
        }
    }
};

