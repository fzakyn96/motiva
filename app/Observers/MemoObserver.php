<?php

namespace App\Observers;

use App\Models\Memo;

class MemoObserver
{
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

            foreach ($policy->steps()->orderBy('step_order')->get() as $step) {
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
