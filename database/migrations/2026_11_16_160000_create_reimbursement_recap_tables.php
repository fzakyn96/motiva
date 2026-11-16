<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('transport_service_fee_percent', 5, 2)->default(0);
            $table->decimal('transport_manfee_percent', 5, 2)->default(5.00);
            $table->decimal('travel_manfee_percent', 5, 2)->default(6.00);
            $table->timestamps();
        });

        Schema::create('transport_accommodation_recaps', function (Blueprint $table) {
            $table->id();
            $table->string('recap_number')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('work_name')->default('Management Enterprise System Infrastructure Information (MESI)');
            $table->string('period_label');
            $table->string('period_month', 50);
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('total_service_fee', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('manfee_percent', 5, 2)->default(5.00);
            $table->decimal('manfee_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_position')->nullable();
            $table->string('checked_by_name')->nullable();
            $table->string('checked_by_position')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transport_accommodation_recap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_accommodation_recap_id')
                ->constrained('transport_accommodation_recaps')
                ->cascadeOnDelete();
            $table->foreignId('sppd_id')->nullable()->constrained('sppds')->noActionOnDelete();
            $table->text('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('service_fee', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->json('evidence_files')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('official_travel_recaps', function (Blueprint $table) {
            $table->id();
            $table->string('recap_number')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('work_name')->default('Management Enterprise System Infrastructure Information (MESI)');
            $table->string('period_label');
            $table->string('period_month', 50);
            $table->decimal('total_work_cost', 15, 2)->default(0);
            $table->decimal('manfee_percent', 5, 2)->default(6.00);
            $table->decimal('manfee_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_position')->nullable();
            $table->json('checked_by_list')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('official_travel_recap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_travel_recap_id')
                ->constrained('official_travel_recaps')
                ->cascadeOnDelete();
            $table->foreignId('sppd_id')->nullable()->constrained('sppds')->noActionOnDelete();
            $table->foreignId('sppd_cost_id')->nullable()->constrained('sppd_costs')->noActionOnDelete();
            $table->text('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_travel_recap_items');
        Schema::dropIfExists('official_travel_recaps');
        Schema::dropIfExists('transport_accommodation_recap_items');
        Schema::dropIfExists('transport_accommodation_recaps');
        Schema::dropIfExists('reimbursement_settings');
    }
};

