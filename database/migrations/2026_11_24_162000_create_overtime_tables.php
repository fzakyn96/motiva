<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_settings', function (Blueprint $table) {
            $table->id();
            $table->json('allowed_creator_roles')->nullable();
            $table->string('fs_number', 20)->default('05');
            $table->string('revision_number', 20)->default('01');
            $table->timestamps();
        });

        Schema::create('overtime_orders', function (Blueprint $table) {
            $table->id();
            $table->string('overtime_number')->nullable()->unique();
            $table->foreignId('employee_id')->constrained('employees')->noActionOnDelete();
            $table->foreignId('creator_employee_id')->nullable()->constrained('employees')->noActionOnDelete();
            $table->foreignId('creator_user_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->string('satuan_kerja');
            $table->string('location_city')->nullable();
            $table->date('overtime_date');
            $table->date('command_date');
            $table->string('overtime_type', 30)->default('Biasa');
            $table->text('work_description');

            // Bagian A: Perintah Kerja Lembur
            $table->string('command_status', 30)->default('pending');
            $table->foreignId('command_approved_by_employee_id')->nullable()->constrained('employees')->noActionOnDelete();
            $table->timestamp('command_approved_at')->nullable();
            $table->text('command_notes')->nullable();

            // Bagian B: Pertanggungjawaban Kerja Lembur
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->text('actual_result')->nullable();
            $table->date('report_date')->nullable();
            $table->string('report_status', 30)->default('not_submitted');
            $table->timestamp('report_submitted_at')->nullable();
            $table->foreignId('report_approved_by_employee_id')->nullable()->constrained('employees')->noActionOnDelete();
            $table->timestamp('report_approved_at')->nullable();
            $table->text('report_notes')->nullable();

            $table->string('status', 30)->default('command_pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_orders');
        Schema::dropIfExists('overtime_settings');
    }
};
