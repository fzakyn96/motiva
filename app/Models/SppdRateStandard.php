<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SppdRateStandard extends Model
{
    use HasFactory;

    protected $fillable = [
        'regulation_code',
        'category_code',
        'category_name',
        'description',

        // Transport
        'transport_terminal_rate',
        'transport_terminal_default_qty',
        'transport_ticket_rate',
        'transport_local_rate',

        // Harian (Per Diem)
        'meal_rate',
        'meal_default_rule',
        'pocket_money_rate',
        'pocket_money_default_rule',
        'local_transit_rate',
        'local_transit_default_qty',
        'hotel_rate',
        'hotel_default_rule',

        'is_active',
        'order_column',
    ];

    protected function casts(): array
    {
        return [
            'transport_terminal_rate' => 'decimal:2',
            'transport_terminal_default_qty' => 'integer',
            'transport_ticket_rate' => 'decimal:2',
            'transport_local_rate' => 'decimal:2',
            'meal_rate' => 'decimal:2',
            'pocket_money_rate' => 'decimal:2',
            'local_transit_rate' => 'decimal:2',
            'local_transit_default_qty' => 'integer',
            'hotel_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'order_column' => 'integer',
        ];
    }

    /**
     * Hitung nilai tarif default berdasarkan durasi hari dan aturan template
     */
    public function calculateDefaultRates(int $days, array $template): array
    {
        $days = max(1, $days);

        $hotelQty = match ($this->hotel_default_rule) {
            'days_minus_one' => max(0, $days - 1),
            'same_as_days' => $days,
            default => 0,
        };

        $mealQty = match ($this->meal_default_rule) {
            'same_as_days' => $days,
            default => 0,
        };

        $pocketQty = match ($this->pocket_money_default_rule) {
            'same_as_days' => $days,
            default => 0,
        };

        $rates = [
            'transport_terminal_qty' => (int) ($this->transport_terminal_default_qty ?? 2),
            'transport_terminal_rate' => (float) ($this->transport_terminal_rate ?? 0),
            'meal_qty' => $mealQty,
            'meal_rate' => (float) ($this->meal_rate ?? 0),
            'pocket_money_qty' => $pocketQty,
            'pocket_money_rate' => (float) ($this->pocket_money_rate ?? 0),
            'local_transit_qty' => (int) ($this->local_transit_default_qty ?? 1),
            'local_transit_rate' => (float) ($this->local_transit_rate ?? 0),
            'hotel_qty' => $hotelQty,
            'hotel_rate' => (float) ($this->hotel_rate ?? 0),
        ];

        return array_merge($template, $rates);
    }

    /**
     * Dapatkan daftar opsi radio kategori standar yang aktif
     */
    public static function getCategoryOptions(): array
    {
        $standards = static::where('is_active', true)->orderBy('order_column')->get();

        if ($standards->isEmpty()) {
            return [
                'office_base_stay' => 'Office Base (≥ 80 km & Menginap) — Uang Makan Rp145rb, Saku Rp120rb, Angkutan Rp75rb',
                'shift_base_stay' => 'Shift Base (≥ 80 km & Menginap) — Lumpsum Rp256.500/hari',
                'shift_base_no_stay' => 'Shift Base (≥ 80 km & Tidak Menginap) — Lumpsum Rp202.500/hari',
                'custom' => 'Kustom / Penugasan Khusus / Luar Negeri',
            ];
        }

        $options = [];
        foreach ($standards as $std) {
            $desc = $std->category_name;
            $parts = [];
            if ($std->meal_rate > 0) {
                $parts[] = 'Makan Rp' . number_format($std->meal_rate, 0, ',', '.');
            }
            if ($std->pocket_money_rate > 0) {
                $parts[] = 'Saku Rp' . number_format($std->pocket_money_rate, 0, ',', '.');
            }
            if ($std->local_transit_rate > 0) {
                $parts[] = 'Angkutan Rp' . number_format($std->local_transit_rate, 0, ',', '.');
            }
            if (! empty($parts)) {
                $desc .= ' — ' . implode(', ', $parts);
            }
            $options[$std->category_code] = $desc;
        }

        return $options;
    }

    /**
     * Dapatkan daftar nama kategori standar (singkat) untuk filter tabel
     */
    public static function getCategoryNames(): array
    {
        $standards = static::where('is_active', true)->orderBy('order_column')->get();

        if ($standards->isEmpty()) {
            return [
                'office_base_stay' => 'Office Base (Menginap)',
                'shift_base_stay' => 'Shift Base (Menginap)',
                'shift_base_no_stay' => 'Shift Base (Tidak Menginap)',
                'custom' => 'Kustom',
            ];
        }

        return $standards->pluck('category_name', 'category_code')->toArray();
    }
}

