<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_can_have_child_department(): void
    {
        $parent = Department::factory()->create();

        $child = Department::factory()
            ->childOf($parent)
            ->create();

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($child->parent->is($parent));
    }

    public function test_employee_can_belong_to_department_and_position(): void
    {
        $department = Department::factory()->create();
        $position = Position::factory()->create();

        $employee = Employee::factory()
            ->inDepartment($department)
            ->inPosition($position)
            ->create();

        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertTrue($employee->department->is($department));
        $this->assertTrue($employee->position->is($position));
    }

    public function test_employee_can_have_manager_and_manager_can_have_subordinates(): void
    {
        $department = Department::factory()->create();

        $manager = Employee::factory()
            ->inDepartment($department)
            ->create();

        $employee = Employee::factory()
            ->inDepartment($department)
            ->managedBy($manager)
            ->create();

        $this->assertSame($manager->id, $employee->manager_id);
        $this->assertTrue($employee->manager->is($manager));
        $this->assertTrue($manager->subordinates->contains($employee));
    }

    public function test_employee_can_be_inactive(): void
    {
        $employee = Employee::factory()
            ->inactive()
            ->create();

        $this->assertFalse($employee->is_active);
        $this->assertNull($employee->resigned_at);
    }

    public function test_employee_can_be_resigned(): void
    {
        $employee = Employee::factory()
            ->resigned()
            ->create();

        $this->assertFalse($employee->is_active);
        $this->assertNotNull($employee->resigned_at);
    }

    public function test_user_factory_creates_employee_relationship(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->employee_id);
        $this->assertTrue($user->employee->exists);
    }
}
