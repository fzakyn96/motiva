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

        if (empty($memo->date)) {
            $memo->date = now();
        }

        if (empty($memo->title)) {
            $memo->title = $memo->agenda ?? '-';
        }

        // Hanya generate nomor_memo jika memo langsung dibuat dengan status approved (misal seeder)
        if ($memo->status === 'approved' && empty($memo->nomor_memo)) {
            $memo->nomor_memo = $this->generateNomorMemo($memo);
        }
    }

    /**
     * Handle the Memo "updating" event.
     */
    public function updating(Memo $memo): void
    {
        if ($memo->status === 'approved' && empty($memo->nomor_memo)) {
            $memo->nomor_memo = $this->generateNomorMemo($memo);
        }
    }

    protected function generateNomorMemo(Memo $memo): string
    {
        $year = date('Y');
        $month = date('m');

        $dept = \App\Models\Department::find($memo->department_id);
        $deptCode = $dept ? ($dept->code ?? 'MESI') : 'MESI';

        $areaCode = 'JKT';
        if (\Illuminate\Support\Facades\Schema::hasTable('work_areas')) {
            $creator = $memo->creator ?? \App\Models\Employee::with('workArea')->find($memo->creator_employee_id);
            $areaCode = $creator?->workArea?->code ?: null;

            if (! $areaCode && ! empty($memo->participants) && is_array($memo->participants)) {
                $firstParticipantId = is_array($memo->participants[0]) ? ($memo->participants[0]['employee_id'] ?? null) : $memo->participants[0];
                if (is_numeric($firstParticipantId)) {
                    $areaCode = \App\Models\Employee::with('workArea')->find($firstParticipantId)?->workArea?->code;
                }
            }
            $areaCode = $areaCode ?: 'JKT';
        }

        $count = Memo::whereYear('created_at', $year)->whereNotNull('nomor_memo')->count();
        $seq = $count + 1;

        do {
            $nextSeq = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $candidate = "{$nextSeq}/{$deptCode}/{$areaCode}/{$month}/{$year}";
            $seq++;
        } while (Memo::where('nomor_memo', $candidate)->exists());

        return $candidate;
    }

    /**
     * Handle the Memo "created" event.
     */
    public function created(Memo $memo): void
    {
        $policy = \App\Models\ApprovalPolicy::firstOrCreate(
            ['model_type' => \App\Models\Memo::class],
            ['name' => 'Memo Default Policy']
        );

        \App\Models\ApprovalPolicyStep::firstOrCreate(
            ['approval_policy_id' => $policy->id, 'step_order' => 1],
            ['resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class]
        );

        $policy->refresh();

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

        // Hanya generate SPPD jika memo langsung berstatus approved (misal seeder/manual)
        if ($memo->status === 'approved') {
            $this->generateSppdForParticipants($memo);
        }
    }

    /**
     * Handle the Memo "updated" event.
     */
    public function updated(Memo $memo): void
    {
        // Hanya generate SPPD kepada peserta penugasan ketika memo telah disetujui manajer
        if ($memo->status === 'approved') {
            $this->generateSppdForParticipants($memo);
        }
    }

    /**
     * Otomatis membuat BusinessTrip dan SPPD perorangan bagi setiap pegawai yang ditugaskan
     * hanya setelah memo resmi disetujui manajer.
     */
    protected function generateSppdForParticipants(Memo $memo): void
    {
        $participants = $memo->participants;

        if (empty($participants) || ! is_array($participants) || count($participants) === 0) {
            return;
        }

        // 1. Buat atau update BusinessTrip untuk Memo ini
        $trip = \App\Models\BusinessTrip::firstOrCreate(
            ['memo_id' => $memo->id],
            [
                'activity_name' => $memo->agenda ?? ('Penugasan Nota Dinas ' . $memo->nomor_memo),
                'destination' => $memo->location ?? '-',
                'start_date' => $memo->assignment_start_date ?? $memo->date ?? now(),
                'end_date' => $memo->assignment_end_date ?? $memo->assignment_start_date ?? $memo->date ?? now(),
            ]
        );

        $trip->update([
            'activity_name' => $memo->agenda ?? $trip->activity_name,
            'destination' => $memo->location ?? $trip->destination,
            'start_date' => $memo->assignment_start_date ?? $trip->start_date,
            'end_date' => $memo->assignment_end_date ?? $memo->assignment_start_date ?? $trip->end_date,
        ]);

        $year = date('Y');
        $month = date('m');

        // 2. Buat SPPD unik untuk setiap peserta yang ditugaskan
        foreach ($participants as $participant) {
            $employeeId = is_array($participant) ? ($participant['employee_id'] ?? null) : $participant;

            $employee = is_numeric($employeeId)
                ? \App\Models\Employee::find($employeeId)
                : \App\Models\Employee::where('name', $employeeId)
                    ->orWhere('identity_number', $employeeId)
                    ->first();

            if (! $employee) {
                continue;
            }

            // Cek apakah SPPD untuk pegawai ini sudah ada di penugasan ini
            $existing = \App\Models\Sppd::where('business_trip_id', $trip->id)
                ->where('employee_id', $employee->id)
                ->first();

            if (! $existing) {
                $startDate = $memo->assignment_start_date;
                $endDate = $memo->assignment_end_date ?? $memo->assignment_start_date;
                $totalDays = null;
                if ($startDate) {
                    $totalDays = $endDate
                        ? \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1
                        : 1;
                }

                \App\Models\Sppd::create([
                    'business_trip_id' => $trip->id,
                    'employee_id' => $employee->id,
                    'sppd_number' => null,
                    'status' => 'draft',
                    'departure_date' => $startDate,
                    'departure_time' => '08:00',
                    'return_date' => $endDate,
                    'return_time' => '17:00',
                    'total_days' => $totalDays,
                    'origin_location' => $employee->area ?? 'Jakarta',
                    'destination_location' => $memo->location,
                    'assignment_purpose' => $memo->agenda ?? $memo->title,
                ]);
            }
        }
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
