<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $table->dateTime('date')->change();
            $table->dateTime('assignment_start_date')->nullable()->change();
            $table->dateTime('assignment_end_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $table->date('date')->change();
            $table->date('assignment_start_date')->nullable()->change();
            $table->date('assignment_end_date')->nullable()->change();
        });
    }
};
