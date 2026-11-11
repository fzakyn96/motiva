<?php

namespace Database\Seeders;

use App\Models\SppdRateStandard;
use Illuminate\Database\Seeder;

class SppdRateStandardSeeder extends Seeder
{
    public function run(): void
    {
        $standards = [
            [
                'regulation_code' => 'ST-JTK-05',
                'category_code' => 'office_base_stay',
                'category_name' => 'Office Base (≥ 80 km & Menginap)',
                'description' => 'Ketentuan perjalanan dinas reguler kantor pusat/cabang dengan jarak tempuh minimal 80 km dan menginap.',
                'transport_terminal_rate' => 100000,
                'transport_terminal_default_qty' => 2,
                'meal_rate' => 145000,
                'meal_default_rule' => 'same_as_days',
                'pocket_money_rate' => 120000,
                'pocket_money_default_rule' => 'same_as_days',
                'local_transit_rate' => 75000,
                'local_transit_default_qty' => 1,
                'hotel_rate' => 0,
                'hotel_default_rule' => 'days_minus_one',
                'is_active' => true,
                'order_column' => 1,
            ],
            [
                'regulation_code' => 'ST-JTK-05',
                'category_code' => 'shift_base_stay',
                'category_name' => 'Shift Base (≥ 80 km & Menginap)',
                'description' => 'Ketentuan dinas shift base ≥ 80 km dan menginap dengan lumpsum saku, makan & angkutan Rp256.500/hari serta plafon hotel Rp750.000.',
                'transport_terminal_rate' => 112500,
                'transport_terminal_default_qty' => 2,
                'meal_rate' => 0,
                'meal_default_rule' => 'zero',
                'pocket_money_rate' => 256500,
                'pocket_money_default_rule' => 'same_as_days',
                'local_transit_rate' => 0,
                'local_transit_default_qty' => 0,
                'hotel_rate' => 750000,
                'hotel_default_rule' => 'zero',
                'is_active' => true,
                'order_column' => 2,
            ],
            [
                'regulation_code' => 'ST-JTK-05',
                'category_code' => 'shift_base_no_stay',
                'category_name' => 'Shift Base (≥ 80 km & Tidak Menginap)',
                'description' => 'Ketentuan dinas shift base ≥ 80 km dan tidak menginap dengan lumpsum saku, makan & angkutan Rp202.500/hari.',
                'transport_terminal_rate' => 112500,
                'transport_terminal_default_qty' => 2,
                'meal_rate' => 0,
                'meal_default_rule' => 'zero',
                'pocket_money_rate' => 202500,
                'pocket_money_default_rule' => 'same_as_days',
                'local_transit_rate' => 0,
                'local_transit_default_qty' => 0,
                'hotel_rate' => 0,
                'hotel_default_rule' => 'zero',
                'is_active' => true,
                'order_column' => 3,
            ],
            [
                'regulation_code' => 'KUSTOM',
                'category_code' => 'custom',
                'category_name' => 'Kustom / Penugasan Khusus / Luar Negeri',
                'description' => 'Ketentuan fleksibel untuk penugasan dinas khusus atau luar negeri dengan pengisian manual.',
                'transport_terminal_rate' => 0,
                'transport_terminal_default_qty' => 0,
                'meal_rate' => 0,
                'meal_default_rule' => 'zero',
                'pocket_money_rate' => 0,
                'pocket_money_default_rule' => 'zero',
                'local_transit_rate' => 0,
                'local_transit_default_qty' => 0,
                'hotel_rate' => 0,
                'hotel_default_rule' => 'zero',
                'is_active' => true,
                'order_column' => 4,
            ],
        ];

        foreach ($standards as $data) {
            SppdRateStandard::updateOrCreate(
                ['category_code' => $data['category_code']],
                $data
            );
        }
    }
}
