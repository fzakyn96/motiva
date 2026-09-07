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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')
                ->nullable(false)
                ->after('id')
                ->constrained('employees')
                ->noActionOnDelete();
            $table->string('username', 100)
                ->after('employee_id');
            $table->boolean('is_active')
                ->default(true)
                ->after('password');
            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');

            $table->unique('employee_id');
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
