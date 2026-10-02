<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Services\Approval\ApprovalResolver;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $this->dept = Department::create(['organization_id' => $this->org->id, 'name' => 'IT', 'code' => 'IT']);
        $this->position = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);

        $this->manager = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'name' => 'Manager User',
            'identity_number' => 'MGR001',
        ]);

        $this->staff = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'manager_id' => $this->manager->id,
            'name' => 'Staff User',
            'identity_number' => 'STF001',
        ]);

        $this->policy = ApprovalPolicy::create([
            'name' => 'Manager Approval Only',
            'model_type' => 'App\Models\Memo',
        ]);

        $this->policyStep = ApprovalPolicyStep::create([
            'approval_policy_id' => $this->policy->id,
            'step_order' => 1,
            'resolver_class' => ManagerAuthorityResolver::class,
        ]);
    }

    public function test_manager_can_approve_request()
    {
        $request = ApprovalRequest::create([
            'document_type' => 'App\Models\Memo',
            'document_id' => 1,
            'approval_policy_id' => $this->policy->id,
            'requester_employee_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $step = ApprovalStep::create([
            'approval_request_id' => $request->id,
            'approval_policy_step_id' => $this->policyStep->id,
            'status' => 'pending',
        ]);

        $resolver = new ApprovalResolver();
        
        $this->assertTrue($resolver->canApprove($request, $this->manager));
        $this->assertFalse($resolver->canApprove($request, $this->staff)); // Staff cannot approve their own request as manager

        $resolver->approve($request, $this->manager, 'Approved by manager');

        $this->assertDatabaseHas('approval_steps', [
            'id' => $step->id,
            'status' => 'approved',
            'approver_employee_id' => $this->manager->id,
        ]);

        $this->assertDatabaseHas('approval_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);
    }

    public function test_unauthorized_user_is_rejected_from_approving()
    {
        $request = ApprovalRequest::create([
            'document_type' => 'App\Models\Memo',
            'document_id' => 1,
            'approval_policy_id' => $this->policy->id,
            'requester_employee_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        ApprovalStep::create([
            'approval_request_id' => $request->id,
            'approval_policy_step_id' => $this->policyStep->id,
            'status' => 'pending',
        ]);

        $resolver = new ApprovalResolver();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized to approve this request.');
        
        $resolver->approve($request, $this->staff);
    }
    public function test_double_approval_is_rejected()
    {
        $request = ApprovalRequest::create([
            'document_type' => 'App\Models\Memo',
            'document_id' => 1,
            'approval_policy_id' => $this->policy->id,
            'requester_employee_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $step = ApprovalStep::create([
            'approval_request_id' => $request->id,
            'approval_policy_step_id' => $this->policyStep->id,
            'status' => 'pending',
        ]);

        $resolver = new ApprovalResolver();
        $resolver->approve($request, $this->manager, 'First approval');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized to approve this request.');
        
        $resolver->approve($request, $this->manager, 'Second approval');
    }

    public function test_step_cannot_be_skipped()
    {
        $policyStep2 = ApprovalPolicyStep::create([
            'approval_policy_id' => $this->policy->id,
            'step_order' => 2,
            'resolver_class' => ManagerAuthorityResolver::class,
        ]);

        $request = ApprovalRequest::create([
            'document_type' => 'App\Models\Memo',
            'document_id' => 1,
            'approval_policy_id' => $this->policy->id,
            'requester_employee_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $step1 = ApprovalStep::create([
            'approval_request_id' => $request->id,
            'approval_policy_step_id' => $this->policyStep->id,
            'status' => 'pending',
        ]);

        $step2 = ApprovalStep::create([
            'approval_request_id' => $request->id,
            'approval_policy_step_id' => $policyStep2->id,
            'status' => 'pending',
        ]);

        $resolver = new ApprovalResolver();
        
        // Approve first step
        $resolver->approve($request, $this->manager, 'First step');
        $this->assertEquals('approved', $step1->fresh()->status);
        $this->assertEquals('pending', $step2->fresh()->status);
        $this->assertEquals('pending', $request->fresh()->status);

        // Approve second step
        $resolver->approve($request, $this->manager, 'Second step');
        $this->assertEquals('approved', $step2->fresh()->status);
        $this->assertEquals('approved', $request->fresh()->status);
    }
}
