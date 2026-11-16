<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_accommodation_recaps', function (Blueprint $table) {
            $table->foreignId('business_trip_id')->nullable()->after('department_id')->constrained('business_trips')->nullOnDelete();
        });

        Schema::table('official_travel_recaps', function (Blueprint $table) {
            $table->foreignId('business_trip_id')->nullable()->after('department_id')->constrained('business_trips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transport_accommodation_recaps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_trip_id');
        });

        Schema::table('official_travel_recaps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_trip_id');
        });
    }
};

