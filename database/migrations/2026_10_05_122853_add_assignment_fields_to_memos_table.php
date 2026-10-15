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
            $table->date('assignment_start_date')->nullable();
            $table->date('assignment_end_date')->nullable();
            $table->string('agenda')->nullable();
            $table->string('location')->nullable();
            $table->json('participants')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $table->dropColumn([
                'assignment_start_date',
                'assignment_end_date',
                'agenda',
                'location',
                'participants',
            ]);
        });
    }
};
