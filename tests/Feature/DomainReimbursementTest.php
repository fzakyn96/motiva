<?php

namespace Tests\Feature;

use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Reimbursement;
use App\Models\Sppd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainReimbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_reimbursement_with_expenses()
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
            'status' => 'approved',
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
            'status' => 'approved',
        ]);

        $reimbursement = Reimbursement::create([
            'sppd_id' => $sppd->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);

        $expense1 = Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Flight',
            'amount' => 1500000,
            'is_company_paid' => true, // Company paid
        ]);

        $expense2 = Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Meals',
            'amount' => 300000,
            'is_company_paid' => false, // Employee paid
        ]);

        $this->assertDatabaseHas('reimbursements', ['id' => $reimbursement->id, 'sppd_id' => $sppd->id]);
        $this->assertDatabaseHas('expenses', ['id' => $expense1->id, 'expense_type' => 'Flight', 'is_company_paid' => true]);
        $this->assertDatabaseHas('expenses', ['id' => $expense2->id, 'expense_type' => 'Meals', 'is_company_paid' => false]);
        
        $this->assertEquals(2, $reimbursement->expenses()->count());
    }
}
