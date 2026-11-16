<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sppd_costs', function (Blueprint $table) {
            $table->json('transport_evidence')->nullable();
            $table->json('per_diem_evidence')->nullable();
            $table->json('other_evidence')->nullable();
            $table->json('general_evidence')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sppd_costs', function (Blueprint $table) {
            $table->dropColumn([
                'transport_evidence',
                'per_diem_evidence',
                'other_evidence',
                'general_evidence',
            ]);
        });
    }
};

