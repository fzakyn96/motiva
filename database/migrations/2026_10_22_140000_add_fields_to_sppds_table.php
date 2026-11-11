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
        Schema::table('sppds', function (Blueprint $table) {
            $table->string('travel_grade')->nullable()->after('sppd_number'); // Golongan Perjalanan Dinas
            $table->date('departure_date')->nullable()->after('travel_grade'); // Tanggal Berangkat
            $table->string('departure_time')->nullable()->after('departure_date'); // Jam Berangkat
            $table->date('return_date')->nullable()->after('departure_time'); // Tanggal Kembali
            $table->string('return_time')->nullable()->after('return_date'); // Jam Kembali
            $table->integer('total_days')->nullable()->after('return_time'); // Jumlah Hari
            $table->string('origin_location')->nullable()->after('total_days'); // Tempat Kedudukan
            $table->string('destination_location')->nullable()->after('origin_location'); // Lokasi Penugasan
            $table->text('assignment_purpose')->nullable()->after('destination_location'); // Deskripsi Tujuan Penugasan
            $table->string('travel_category')->nullable()->after('assignment_purpose'); // Kategori Perjalanan Dinas (Dalam Negeri, Khusus, Luar Negeri)
            $table->string('general_travel_type')->nullable()->after('travel_category'); // Jenis Perjalanan Dinas Umum
            $table->string('special_travel_type')->nullable()->after('general_travel_type'); // Jenis Perjalanan Dinas Khusus
            $table->string('transport_departure')->nullable()->after('special_travel_type'); // Transportasi Keberangkatan (Sendiri, Kendaraan Dinas)
            $table->string('transport_arrival')->nullable()->after('transport_departure'); // Transportasi Kedatangan (Sendiri, Kendaraan Dinas)
            $table->string('transportation_mode')->nullable()->after('transport_arrival'); // Sarana Transportasi (PU, KA, TUD, KL)
            $table->json('destination_transportation')->nullable()->after('transportation_mode'); // Sarana Transportasi di Tujuan (Array)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sppds', function (Blueprint $table) {
            $table->dropColumn([
                'travel_grade',
                'departure_date',
                'departure_time',
                'return_date',
                'return_time',
                'total_days',
                'origin_location',
                'destination_location',
                'assignment_purpose',
                'travel_category',
                'general_travel_type',
                'special_travel_type',
                'transport_departure',
                'transport_arrival',
                'transportation_mode',
                'destination_transportation',
            ]);
        });
    }
};
