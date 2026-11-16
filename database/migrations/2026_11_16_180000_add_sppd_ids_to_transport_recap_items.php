<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_accommodation_recap_items', function (Blueprint $table) {
            $table->json('sppd_ids')->nullable()->after('sppd_id');
        });
    }

    public function down(): void
    {
        Schema::table('transport_accommodation_recap_items', function (Blueprint $table) {
            $table->dropColumn('sppd_ids');
        });
    }
};

