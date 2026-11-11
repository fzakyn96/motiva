<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SppdRateStandardResource;
use App\Filament\Admin\Resources\SppdRateStandardResource\Pages\CreateSppdRateStandard;
use App\Filament\Admin\Resources\SppdRateStandardResource\Pages\EditSppdRateStandard;
use App\Filament\Admin\Resources\SppdRateStandardResource\Pages\ListSppdRateStandards;
use App\Models\SppdCost;
use App\Models\SppdRateStandard;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SppdRateStandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DomainSppdRateStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SppdRateStandardSeeder::class);
    }

    public function test_hr_can_access_sppd_rate_standards_list_page(): void
    {
        $hrUser = User::factory()->create();
        $hrUser->assignRole('HR');

        $this->actingAs($hrUser);

        $this->assertTrue(SppdRateStandardResource::canViewAny());
        $this->assertTrue(SppdRateStandardResource::canCreate());

        Livewire::test(ListSppdRateStandards::class)
            ->assertSuccessful()
            ->assertSee('Office Base (≥ 80 km & Menginap)')
            ->assertSee('Shift Base (≥ 80 km & Menginap)')
            ->assertSee('ST-JTK-05');
    }

    public function test_regular_staff_cannot_access_sppd_rate_standards_list_page(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole('Staff');

        $this->actingAs($staffUser);

        $this->assertFalse(SppdRateStandardResource::canViewAny());
        $this->assertFalse(SppdRateStandardResource::canCreate());
    }

    public function test_changing_master_rate_dynamically_affects_sppd_cost_defaults_without_code_changes(): void
    {
        // Default awal office_base_stay dari seeder:
        // meal_rate = 145000, pocket_money_rate = 120000, local_transit_rate = 75000, hotel_rate = 0 (by receipt), hotel_qty = days - 1
        $initialDefaults = SppdCost::getDefaultRates('office_base_stay', 3);
        $this->assertEquals(145000, $initialDefaults['meal_rate']);
        $this->assertEquals(120000, $initialDefaults['pocket_money_rate']);
        $this->assertEquals(75000, $initialDefaults['local_transit_rate']);
        $this->assertEquals(0, $initialDefaults['hotel_rate']);
        $this->assertEquals(3, $initialDefaults['meal_qty']);
        $this->assertEquals(2, $initialDefaults['hotel_qty']); // 3 - 1

        // Manajemen memperbarui tarif standar ST-JTK-05 di master:
        $officeRate = SppdRateStandard::where('category_code', 'office_base_stay')->first();
        $officeRate->update([
            'meal_rate' => 175000,
            'pocket_money_rate' => 150000,
            'hotel_rate' => 550000,
            'local_transit_rate' => 90000,
            'transport_terminal_rate' => 125000,
        ]);

        // Hitung ulang default rates untuk 4 hari dinas
        $updatedDefaults = SppdCost::getDefaultRates('office_base_stay', 4);

        // Nilai harus langsung ter-update secara dinamis dari master database tanpa hardcode!
        $this->assertEquals(175000, $updatedDefaults['meal_rate']);
        $this->assertEquals(150000, $updatedDefaults['pocket_money_rate']);
        $this->assertEquals(550000, $updatedDefaults['hotel_rate']);
        $this->assertEquals(90000, $updatedDefaults['local_transit_rate']);
        $this->assertEquals(125000, $updatedDefaults['transport_terminal_rate']);
        $this->assertEquals(4, $updatedDefaults['meal_qty']);
        $this->assertEquals(3, $updatedDefaults['hotel_qty']); // 4 - 1
    }

    public function test_can_create_new_tariff_standard_category_and_use_in_calculation(): void
    {
        // Buat ketentuan tarif baru misal: offshore_rig
        SppdRateStandard::create([
            'regulation_code' => 'ST-JTK-06',
            'category_code' => 'offshore_rig',
            'category_name' => 'Offshore Rig Platform',
            'description' => 'Tarif khusus penugasan offshore',
            'transport_terminal_rate' => 200000,
            'transport_terminal_default_qty' => 2,
            'meal_rate' => 300000,
            'meal_default_rule' => 'same_as_days',
            'pocket_money_rate' => 250000,
            'pocket_money_default_rule' => 'same_as_days',
            'local_transit_rate' => 100000,
            'local_transit_default_qty' => 1,
            'hotel_rate' => 0,
            'hotel_default_rule' => 'none',
            'is_active' => true,
            'order_column' => 5,
        ]);

        $options = SppdRateStandard::getCategoryOptions();
        $this->assertArrayHasKey('offshore_rig', $options);
        $this->assertStringContainsString('Offshore Rig Platform', $options['offshore_rig']);
        $this->assertStringContainsString('Makan Rp300.000', $options['offshore_rig']);

        $rates = SppdCost::getDefaultRates('offshore_rig', 5);
        $this->assertEquals(300000, $rates['meal_rate']);
        $this->assertEquals(5, $rates['meal_qty']);
        $this->assertEquals(250000, $rates['pocket_money_rate']);
        $this->assertEquals(5, $rates['pocket_money_qty']);
        $this->assertEquals(0, $rates['hotel_rate']);
        $this->assertEquals(0, $rates['hotel_qty']);
    }

    public function test_inactive_standard_is_excluded_from_active_options(): void
    {
        $shiftStay = SppdRateStandard::where('category_code', 'shift_base_stay')->first();
        $shiftStay->update(['is_active' => false]);

        $options = SppdRateStandard::getCategoryOptions();
        $this->assertArrayNotHasKey('shift_base_stay', $options);

        $names = SppdRateStandard::getCategoryNames();
        $this->assertArrayNotHasKey('shift_base_stay', $names);
    }

    public function test_hr_can_update_standard_rate_via_filament_form(): void
    {
        $hrUser = User::factory()->create();
        $hrUser->assignRole('HR');

        $this->actingAs($hrUser);

        $record = SppdRateStandard::where('category_code', 'office_base_stay')->first();

        Livewire::test(EditSppdRateStandard::class, ['record' => $record->getKey()])
            ->fillForm([
                'meal_rate' => 180000,
                'pocket_money_rate' => 135000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sppd_rate_standards', [
            'id' => $record->id,
            'meal_rate' => 180000,
            'pocket_money_rate' => 135000,
        ]);
    }
}
