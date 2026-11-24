<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'overtime_orders_overtime_number_unique' AND object_id = OBJECT_ID('overtime_orders')) DROP INDEX [overtime_orders_overtime_number_unique] ON [overtime_orders]");
            DB::statement("CREATE UNIQUE NONCLUSTERED INDEX [overtime_orders_overtime_number_unique] ON [overtime_orders] ([overtime_number]) WHERE [overtime_number] IS NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'overtime_orders_overtime_number_unique' AND object_id = OBJECT_ID('overtime_orders')) DROP INDEX [overtime_orders_overtime_number_unique] ON [overtime_orders]");
            Schema::table('overtime_orders', function (Blueprint $table): void {
                $table->unique('overtime_number');
            });
        }
    }
};
