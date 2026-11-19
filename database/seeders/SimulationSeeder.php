<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SimulationSeeder extends Seeder
{
    public function run(): void
    {
        $org = \App\Models\Organization::updateOrCreate(['code' => 'PGN'], ['name' => 'PT. Perusahaan Gas Negara (Persero) Tbk.']);
        $dept = \App\Models\Department::updateOrCreate(
            ['code' => 'MESI'],
            ['name' => 'Management Enterprise System Infrastructure Information (MESI)', 'organization_id' => $org->id]
        );
        
        $mgrPos = \App\Models\Position::firstOrCreate(['code' => 'MGR'], ['name' => 'Manager', 'level' => 1]);
        $stfPos = \App\Models\Position::firstOrCreate(['code' => 'STF'], ['name' => 'Staff', 'level' => 3]);

        $manager = \App\Models\Employee::firstOrCreate(['identity_number' => 'MGR01'], [
            'name' => 'Pak Manager',
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
        ]);

        $staff = \App\Models\Employee::firstOrCreate(['identity_number' => 'STF01'], [
            'name' => 'Mas Staff',
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'manager_id' => $manager->id,
        ]);

        $policy = \App\Models\ApprovalPolicy::firstOrCreate(['model_type' => \App\Models\Memo::class], [
            'name' => 'Memo Default Policy'
        ]);

        \App\Models\ApprovalPolicyStep::firstOrCreate(['approval_policy_id' => $policy->id, 'step_order' => 1], [
            'resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class
        ]);

        // SPPD Approval Policy: Step 1 (Supervisor) -> Step 2 (Manager)
        $sppdPolicy = \App\Models\ApprovalPolicy::firstOrCreate(['model_type' => \App\Models\Sppd::class], [
            'name' => 'SPPD Default Policy'
        ]);

        \App\Models\ApprovalPolicyStep::firstOrCreate(['approval_policy_id' => $sppdPolicy->id, 'step_order' => 1], [
            'resolver_class' => \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class
        ]);

        \App\Models\ApprovalPolicyStep::firstOrCreate(['approval_policy_id' => $sppdPolicy->id, 'step_order' => 2], [
            'resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class
        ]);

        // Reimbursement Approval Policy: Step 1 (Manager)
        $reimbPolicy = \App\Models\ApprovalPolicy::firstOrCreate(['model_type' => \App\Models\Reimbursement::class], [
            'name' => 'Reimbursement Default Policy'
        ]);

        \App\Models\ApprovalPolicyStep::firstOrCreate(['approval_policy_id' => $reimbPolicy->id, 'step_order' => 1], [
            'resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class
        ]);
    }
}
