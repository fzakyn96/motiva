<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_accommodation_recaps', function (Blueprint $table) {
            $table->json('business_trip_ids')->nullable()->after('business_trip_id');
        });

        Schema::table('official_travel_recaps', function (Blueprint $table) {
            $table->json('business_trip_ids')->nullable()->after('business_trip_id');
        });
    }

    public function down(): void
    {
        Schema::table('transport_accommodation_recaps', function (Blueprint $table) {
            $table->dropColumn('business_trip_ids');
        });

        Schema::table('official_travel_recaps', function (Blueprint $table) {
            $table->dropColumn('business_trip_ids');
        });
    }
};

