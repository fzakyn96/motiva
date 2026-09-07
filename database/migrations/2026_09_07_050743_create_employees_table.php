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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 50);
            $table->string('nip', 50)->nullable();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->noActionOnDelete();
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions')
                ->noActionOnDelete();
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('employees')
                ->noActionOnDelete();
            $table->boolean('is_active')->default(true);
            $table->date('joined_at')->nullable();
            $table->date('resigned_at')->nullable();
            $table->timestamps();

            $table->unique('employee_code');
            $table->index(['department_id', 'is_active']);
            $table->index(['position_id', 'is_active']);
            $table->index(['manager_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
