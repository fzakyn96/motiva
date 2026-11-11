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
        Schema::create('sppd_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sppd_id')->unique()->constrained('sppds')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Kategori perhitungan & durasi
            $table->string('base_type')->default('office_base_stay'); // office_base_stay, shift_base_stay, shift_base_no_stay, custom
            $table->integer('official_days')->default(1);
            
            // 1. Biaya Transport
            $table->integer('transport_terminal_qty')->default(0);
            $table->decimal('transport_terminal_rate', 15, 2)->default(0);
            $table->decimal('transport_terminal_total', 15, 2)->default(0);

            $table->integer('transport_ticket_qty')->default(0);
            $table->decimal('transport_ticket_rate', 15, 2)->default(0);
            $table->decimal('transport_ticket_total', 15, 2)->default(0);

            $table->integer('transport_local_qty')->default(0);
            $table->decimal('transport_local_rate', 15, 2)->default(0);
            $table->decimal('transport_local_total', 15, 2)->default(0);

            $table->decimal('subtotal_transport', 15, 2)->default(0);

            // 2. Jenis Biaya (Harian Dinas Dalam Negeri & Luar Negeri)
            $table->integer('hotel_qty')->default(0);
            $table->decimal('hotel_rate', 15, 2)->default(0);
            $table->decimal('hotel_total', 15, 2)->default(0);

            $table->integer('meal_qty')->default(0);
            $table->decimal('meal_rate', 15, 2)->default(0);
            $table->decimal('meal_total', 15, 2)->default(0);

            $table->integer('meal_reimbursement_qty')->default(0);
            $table->decimal('meal_reimbursement_rate', 15, 2)->default(0);
            $table->decimal('meal_reimbursement_total', 15, 2)->default(0);

            $table->integer('local_transit_qty')->default(0);
            $table->decimal('local_transit_rate', 15, 2)->default(0);
            $table->decimal('local_transit_total', 15, 2)->default(0);

            $table->integer('pocket_money_qty')->default(0);
            $table->decimal('pocket_money_rate', 15, 2)->default(0);
            $table->decimal('pocket_money_total', 15, 2)->default(0);

            // Luar Negeri
            $table->decimal('overseas_daily_allowance', 15, 2)->default(0);
            $table->decimal('overseas_hotel_meal', 15, 2)->default(0);
            $table->decimal('overseas_transport', 15, 2)->default(0);
            $table->decimal('overseas_pocket_money', 15, 2)->default(0);
            $table->decimal('overseas_monthly_allowance', 15, 2)->default(0);

            $table->decimal('subtotal_per_diem', 15, 2)->default(0);

            // 3. Biaya Lain
            $table->decimal('other_airport_tax', 15, 2)->default(0);
            $table->decimal('other_laundry', 15, 2)->default(0);
            $table->decimal('other_equipment', 15, 2)->default(0);
            $table->decimal('other_book_paper', 15, 2)->default(0);
            $table->decimal('other_assessor', 15, 2)->default(0);
            $table->decimal('other_car_rental', 15, 2)->default(0);
            $table->decimal('other_baggage', 15, 2)->default(0);
            $table->decimal('other_toll_fuel_parking', 15, 2)->default(0);

            $table->decimal('subtotal_other', 15, 2)->default(0);

            // Total Biaya Disetujui
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('status')->default('final'); // draft, final, paid
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sppd_costs');
    }
};
