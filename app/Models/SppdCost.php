<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppdCost extends Model
{
    use HasFactory;

    public static array $numericFields = [
        'official_days',
        'transport_terminal_qty', 'transport_terminal_rate', 'transport_terminal_total',
        'transport_ticket_qty', 'transport_ticket_rate', 'transport_ticket_total',
        'transport_local_qty', 'transport_local_rate', 'transport_local_total',
        'subtotal_transport',
        'hotel_qty', 'hotel_rate', 'hotel_total',
        'meal_qty', 'meal_rate', 'meal_total',
        'meal_reimbursement_qty', 'meal_reimbursement_rate', 'meal_reimbursement_total',
        'local_transit_qty', 'local_transit_rate', 'local_transit_total',
        'pocket_money_qty', 'pocket_money_rate', 'pocket_money_total',
        'overseas_daily_allowance', 'overseas_hotel_meal', 'overseas_transport',
        'overseas_pocket_money', 'overseas_monthly_allowance', 'subtotal_per_diem',
        'other_airport_tax', 'other_laundry', 'other_equipment',
        'other_book_paper', 'other_assessor', 'other_car_rental',
        'other_baggage', 'other_toll_fuel_parking', 'subtotal_other',
        'total_cost',
    ];

    protected static function booted(): void
    {
        static::saving(function (SppdCost $cost) {
            foreach (static::$numericFields as $field) {
                if ($cost->{$field} === null || $cost->{$field} === '') {
                    $cost->{$field} = 0;
                }
            }
        });
    }

    protected $fillable = [
        'sppd_id',
        'creator_id',
        'base_type',
        'official_days',

        // 1. Biaya Transport
        'transport_terminal_qty',
        'transport_terminal_rate',
        'transport_terminal_total',
        'transport_ticket_qty',
        'transport_ticket_rate',
        'transport_ticket_total',
        'transport_local_qty',
        'transport_local_rate',
        'transport_local_total',
        'subtotal_transport',

        // 2. Jenis Biaya
        'hotel_qty',
        'hotel_rate',
        'hotel_total',
        'meal_qty',
        'meal_rate',
        'meal_total',
        'meal_reimbursement_qty',
        'meal_reimbursement_rate',
        'meal_reimbursement_total',
        'local_transit_qty',
        'local_transit_rate',
        'local_transit_total',
        'pocket_money_qty',
        'pocket_money_rate',
        'pocket_money_total',

        // Luar Negeri
        'overseas_daily_allowance',
        'overseas_hotel_meal',
        'overseas_transport',
        'overseas_pocket_money',
        'overseas_monthly_allowance',
        'subtotal_per_diem',

        // 3. Biaya Lain
        'other_airport_tax',
        'other_laundry',
        'other_equipment',
        'other_book_paper',
        'other_assessor',
        'other_car_rental',
        'other_baggage',
        'other_toll_fuel_parking',
        'subtotal_other',

        // Grand Total & Status
        'total_cost',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'official_days' => 'integer',
            'transport_terminal_qty' => 'integer',
            'transport_terminal_rate' => 'decimal:2',
            'transport_terminal_total' => 'decimal:2',
            'transport_ticket_qty' => 'integer',
            'transport_ticket_rate' => 'decimal:2',
            'transport_ticket_total' => 'decimal:2',
            'transport_local_qty' => 'integer',
            'transport_local_rate' => 'decimal:2',
            'transport_local_total' => 'decimal:2',
            'subtotal_transport' => 'decimal:2',

            'hotel_qty' => 'integer',
            'hotel_rate' => 'decimal:2',
            'hotel_total' => 'decimal:2',
            'meal_qty' => 'integer',
            'meal_rate' => 'decimal:2',
            'meal_total' => 'decimal:2',
            'meal_reimbursement_qty' => 'integer',
            'meal_reimbursement_rate' => 'decimal:2',
            'meal_reimbursement_total' => 'decimal:2',
            'local_transit_qty' => 'integer',
            'local_transit_rate' => 'decimal:2',
            'local_transit_total' => 'decimal:2',
            'pocket_money_qty' => 'integer',
            'pocket_money_rate' => 'decimal:2',
            'pocket_money_total' => 'decimal:2',

            'overseas_daily_allowance' => 'decimal:2',
            'overseas_hotel_meal' => 'decimal:2',
            'overseas_transport' => 'decimal:2',
            'overseas_pocket_money' => 'decimal:2',
            'overseas_monthly_allowance' => 'decimal:2',
            'subtotal_per_diem' => 'decimal:2',

            'other_airport_tax' => 'decimal:2',
            'other_laundry' => 'decimal:2',
            'other_equipment' => 'decimal:2',
            'other_book_paper' => 'decimal:2',
            'other_assessor' => 'decimal:2',
            'other_car_rental' => 'decimal:2',
            'other_baggage' => 'decimal:2',
            'other_toll_fuel_parking' => 'decimal:2',
            'subtotal_other' => 'decimal:2',

            'total_cost' => 'decimal:2',
        ];
    }

    public function sppd(): BelongsTo
    {
        return $this->belongsTo(Sppd::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Hitung ulang total biaya secara otomatis
     */
    public function recalculateTotals(): void
    {
        $this->transport_terminal_total = (float) $this->transport_terminal_qty * (float) $this->transport_terminal_rate;
        $this->transport_ticket_total = (float) $this->transport_ticket_qty * (float) $this->transport_ticket_rate;
        $this->transport_local_total = (float) $this->transport_local_qty * (float) $this->transport_local_rate;
        $this->subtotal_transport = (float) $this->transport_terminal_total + (float) $this->transport_ticket_total + (float) $this->transport_local_total;

        $this->hotel_total = (float) $this->hotel_qty * (float) $this->hotel_rate;
        $this->meal_total = (float) $this->meal_qty * (float) $this->meal_rate;
        $this->meal_reimbursement_total = (float) $this->meal_reimbursement_qty * (float) $this->meal_reimbursement_rate;
        $this->local_transit_total = (float) $this->local_transit_qty * (float) $this->local_transit_rate;
        $this->pocket_money_total = (float) $this->pocket_money_qty * (float) $this->pocket_money_rate;

        $overseasTotal = (float) $this->overseas_daily_allowance +
            (float) $this->overseas_hotel_meal +
            (float) $this->overseas_transport +
            (float) $this->overseas_pocket_money +
            (float) $this->overseas_monthly_allowance;

        $this->subtotal_per_diem = (float) $this->hotel_total +
            (float) $this->meal_total +
            (float) $this->meal_reimbursement_total +
            (float) $this->local_transit_total +
            (float) $this->pocket_money_total +
            $overseasTotal;

        $this->subtotal_other = (float) $this->other_airport_tax +
            (float) $this->other_laundry +
            (float) $this->other_equipment +
            (float) $this->other_book_paper +
            (float) $this->other_assessor +
            (float) $this->other_car_rental +
            (float) $this->other_baggage +
            (float) $this->other_toll_fuel_parking;

        $this->total_cost = (float) $this->subtotal_transport + (float) $this->subtotal_per_diem + (float) $this->subtotal_other;
    }

    public static function getEmptyRatesTemplate(): array
    {
        $template = [];
        foreach (static::$numericFields as $field) {
            $template[$field] = 0;
        }
        return $template;
    }

    /**
     * Dapatkan default tarif standar sesuai Master Tarif Standar (ST-JTK-05)
     */
    public static function getDefaultRates(string $baseType = 'office_base_stay', int $days = 1): array
    {
        $days = max(1, $days);
        $template = static::getEmptyRatesTemplate();

        // 1. Ambil dari Master Data Tarif Standar jika ada
        if (class_exists(\App\Models\SppdRateStandard::class)) {
            $standard = \App\Models\SppdRateStandard::where('category_code', $baseType)
                ->where('is_active', true)
                ->first();

            if ($standard) {
                return $standard->calculateDefaultRates($days, $template);
            }
        }

        switch ($baseType) {
            case 'shift_base_stay':
                $rates = [
                    'transport_terminal_qty' => 2,
                    'transport_terminal_rate' => 112500, // Bandara
                    'pocket_money_qty' => $days,
                    'pocket_money_rate' => 256500, // Lumpsum saku, makan & angkutan
                    'hotel_rate' => 750000,
                ];
                break;

            case 'shift_base_no_stay':
                $rates = [
                    'transport_terminal_qty' => 2,
                    'transport_terminal_rate' => 112500,
                    'pocket_money_qty' => $days,
                    'pocket_money_rate' => 202500, // Lumpsum saku, makan & angkutan
                ];
                break;

            case 'office_base_stay':
            default:
                $rates = [
                    'transport_terminal_qty' => 2, // PP Bandara/Stasiun
                    'transport_terminal_rate' => 100000, // Bandara
                    'meal_qty' => $days,
                    'meal_rate' => 145000, // Uang makan
                    'pocket_money_qty' => $days,
                    'pocket_money_rate' => 120000, // Uang saku
                    'local_transit_qty' => 1,
                    'local_transit_rate' => 75000, // Uang angkutan setempat
                    'hotel_qty' => max(0, $days - 1),
                    'hotel_rate' => 0, // Sesuai kuitansi (maks 750.000)
                ];
                break;
        }

        return array_merge($template, $rates);
    }
}
