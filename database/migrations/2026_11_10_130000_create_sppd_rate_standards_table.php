<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sppd_rate_standards', function (Blueprint $table) {
            $table->id();
            $table->string('regulation_code')->default('ST-JTK-05');
            $table->string('category_code')->unique();
            $table->string('category_name');
            $table->text('description')->nullable();

            // Transport Default Rates
            $table->decimal('transport_terminal_rate', 15, 2)->default(0);
            $table->integer('transport_terminal_default_qty')->default(2);
            $table->decimal('transport_ticket_rate', 15, 2)->default(0);
            $table->decimal('transport_local_rate', 15, 2)->default(0);

            // Per Diem (Harian) Default Rates
            $table->decimal('meal_rate', 15, 2)->default(0);
            $table->string('meal_default_rule')->default('same_as_days');

            $table->decimal('pocket_money_rate', 15, 2)->default(0);
            $table->string('pocket_money_default_rule')->default('same_as_days');

            $table->decimal('local_transit_rate', 15, 2)->default(0);
            $table->integer('local_transit_default_qty')->default(1);

            $table->decimal('hotel_rate', 15, 2)->default(0);
            $table->string('hotel_default_rule')->default('days_minus_one');

            $table->boolean('is_active')->default(true);
            $table->integer('order_column')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sppd_rate_standards');
    }
};
