<?php

namespace App\Observers;

use App\Models\ApprovalRequest;

class ApprovalRequestObserver
{
    /**
     * Handle the ApprovalRequest "created" event.
     */
    public function created(ApprovalRequest $approvalRequest): void
    {
        //
    }

    /**
     * Handle the ApprovalRequest "updated" event.
     */
    public function updated(ApprovalRequest $approvalRequest): void
    {
        if ($approvalRequest->isDirty('status')) {
            $document = $approvalRequest->document;
            if ($document && in_array($approvalRequest->status, ['approved', 'rejected'])) {
                $document->update(['status' => $approvalRequest->status]);
            }
        }
    }

    /**
     * Handle the ApprovalRequest "deleted" event.
     */
    public function deleted(ApprovalRequest $approvalRequest): void
    {
        //
    }

    /**
     * Handle the ApprovalRequest "restored" event.
     */
    public function restored(ApprovalRequest $approvalRequest): void
    {
        //
    }

    /**
     * Handle the ApprovalRequest "force deleted" event.
     */
    public function forceDeleted(ApprovalRequest $approvalRequest): void
    {
        //
    }
}
