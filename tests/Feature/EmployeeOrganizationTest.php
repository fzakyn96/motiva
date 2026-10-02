<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organization_and_department()
    {
        $org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $dept = Department::create([
            'organization_id' => $org->id,
            'name' => 'IT Department',
            'code' => 'IT'
        ]);

        $this->assertDatabaseHas('organizations', ['code' => 'TEST']);
        $this->assertDatabaseHas('departments', ['code' => 'IT', 'organization_id' => $org->id]);
    }

    public function test_can_create_employee_with_user_and_manager()
    {
        $org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'IT']);
        $position = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);

        $manager = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'name' => 'Manager User',
            'identity_number' => 'EMP001',
        ]);

        $user = User::factory()->create();

        $employee = Employee::create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'manager_id' => $manager->id,
            'name' => 'Staff User',
            'identity_number' => 'EMP002',
        ]);

        $this->assertDatabaseHas('employees', ['identity_number' => 'EMP001']);
        $this->assertDatabaseHas('employees', ['identity_number' => 'EMP002', 'user_id' => $user->id]);
        
        $this->assertEquals($manager->id, $employee->manager->id);
        $this->assertTrue($manager->subordinates->contains($employee));
    }
}
