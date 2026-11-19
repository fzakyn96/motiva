<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->noActionOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('coordinator_employee_id')->nullable()->constrained('employees')->noActionOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('work_area_id')
                ->nullable()
                ->after('department_id')
                ->constrained('work_areas')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['work_area_id']);
            $table->dropColumn('work_area_id');
        });

        Schema::dropIfExists('work_areas');
    }
};
