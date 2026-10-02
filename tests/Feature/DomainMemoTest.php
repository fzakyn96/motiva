<?php

namespace Tests\Feature;

use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Sppd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_memo_business_trip_and_sppd()
    {
        $org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'IT']);
        $position = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);

        $employee = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'name' => 'Traveler User',
            'identity_number' => 'TRV001',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $employee->id,
            'title' => 'Test Memo',
            'content' => 'Test Content',
            'date' => '2026-10-02',
            'status' => 'draft',
        ]);

        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Site Visit',
            'destination' => 'Jakarta',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $employee->id,
            'sppd_number' => 'SPPD-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('memos', ['id' => $memo->id, 'title' => 'Test Memo']);
        $this->assertDatabaseHas('business_trips', ['id' => $trip->id, 'memo_id' => $memo->id]);
        $this->assertDatabaseHas('sppds', ['id' => $sppd->id, 'business_trip_id' => $trip->id]);
        
        $this->assertEquals($memo->id, $trip->memo->id);
        $this->assertEquals($trip->id, $sppd->businessTrip->id);
        $this->assertEquals($employee->id, $sppd->employee->id);
    }
}
