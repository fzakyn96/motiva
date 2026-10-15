<?php

namespace App\Observers;

use App\Models\Memo;

class MemoObserver
{
    /**
     * Handle the Memo "creating" event.
     */
    public function creating(Memo $memo): void
    {
        // Set creator and department from authenticated user if not set
        $employee = auth()->user()?->employee;
        
        if ($employee) {
            if (! $memo->creator_employee_id) {
                $memo->creator_employee_id = $employee->id;
            }
            if (! $memo->department_id) {
                $memo->department_id = $employee->department_id;
            }
        } else {
            // Fallback for seeder/console
            if (! $memo->creator_employee_id) {
                $memo->creator_employee_id = \App\Models\Employee::first()->id ?? 1;
            }
            if (! $memo->department_id) {
                $memo->department_id = \App\Models\Department::first()->id ?? 1;
            }
        }

        if (empty($memo->title)) {
            $memo->title = $memo->agenda ?? '-';
        }

        // Generate nomor_memo
        $year = date('Y');
        $month = date('m');
        
        // Try to get department code
        $dept = \App\Models\Department::find($memo->department_id);
        $deptCode = $dept ? ($dept->code ?? 'GEN') : 'GEN';
        
        // Count memos created this year to determine the sequence number
        $count = Memo::whereYear('created_at', $year)->count();
        $nextSeq = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        $memo->nomor_memo = "{$nextSeq}/MEMO/{$deptCode}/{$month}/{$year}";
    }

    /**
     * Handle the Memo "created" event.
     */
    public function created(Memo $memo): void
    {
        $policy = \App\Models\ApprovalPolicy::where('model_type', \App\Models\Memo::class)->first();
        
        if ($policy) {
            $request = \App\Models\ApprovalRequest::create([
                'document_type' => \App\Models\Memo::class,
                'document_id' => $memo->id,
                'approval_policy_id' => $policy->id,
                'requester_employee_id' => $memo->creator_employee_id,
                'status' => 'pending',
            ]);

            foreach ($policy->steps as $step) {
                \App\Models\ApprovalStep::create([
                    'approval_request_id' => $request->id,
                    'approval_policy_step_id' => $step->id,
                    'status' => 'pending',
                ]);
            }
        }
    }

    /**
     * Handle the Memo "updated" event.
     */
    public function updated(Memo $memo): void
    {
        //
    }

    /**
     * Handle the Memo "deleted" event.
     */
    public function deleted(Memo $memo): void
    {
        //
    }

    /**
     * Handle the Memo "restored" event.
     */
    public function restored(Memo $memo): void
    {
        //
    }

    /**
     * Handle the Memo "force deleted" event.
     */
    public function forceDeleted(Memo $memo): void
    {
        //
    }
}
