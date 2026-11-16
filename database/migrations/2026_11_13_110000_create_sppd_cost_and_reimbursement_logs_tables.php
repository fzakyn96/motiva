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
        Schema::create('sppd_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memo_log_id')->nullable()->index();
            $table->unsignedBigInteger('sppd_id')->nullable()->index();
            $table->string('sppd_number')->nullable()->index();
            $table->string('nomor_memo')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('destination')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->integer('total_days')->nullable();
            $table->string('sppd_status')->default('draft');
            $table->string('action')->default('deleted_via_memo');
            $table->text('deletion_reason');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->index();
            $table->string('deleted_by_name');
            $table->string('deleted_by_role')->nullable();
            $table->json('sppd_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('sppd_cost_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memo_log_id')->nullable()->index();
            $table->unsignedBigInteger('sppd_log_id')->nullable()->index();
            $table->unsignedBigInteger('sppd_cost_id')->nullable()->index();
            $table->unsignedBigInteger('sppd_id')->nullable()->index();
            $table->string('sppd_number')->nullable()->index();
            $table->string('nomor_memo')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('base_type')->nullable();
            $table->integer('official_days')->default(0);
            $table->decimal('subtotal_transport', 15, 2)->default(0);
            $table->decimal('subtotal_per_diem', 15, 2)->default(0);
            $table->decimal('subtotal_other', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('action')->default('deleted_via_memo');
            $table->text('deletion_reason');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->index();
            $table->string('deleted_by_name');
            $table->string('deleted_by_role')->nullable();
            $table->json('cost_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('reimbursement_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memo_log_id')->nullable()->index();
            $table->unsignedBigInteger('sppd_log_id')->nullable()->index();
            $table->unsignedBigInteger('reimbursement_id')->nullable()->index();
            $table->unsignedBigInteger('sppd_id')->nullable()->index();
            $table->string('reimbursement_number')->nullable()->index();
            $table->string('sppd_number')->nullable()->index();
            $table->string('nomor_memo')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('title')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_claim_amount', 15, 2)->default(0);
            $table->integer('expenses_count')->default(0);
            $table->string('reimbursement_status')->default('draft');
            $table->string('action')->default('deleted_via_memo');
            $table->text('deletion_reason');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->index();
            $table->string('deleted_by_name');
            $table->string('deleted_by_role')->nullable();
            $table->json('reimbursement_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursement_logs');
        Schema::dropIfExists('sppd_cost_logs');
        Schema::dropIfExists('sppd_logs');
    }
};

