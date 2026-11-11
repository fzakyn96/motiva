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
                $deptCode = $employee->department?->code ?? ($memo->department?->code ?? 'GEN');
                $count = \App\Models\Sppd::whereYear('created_at', $year)->count();
                $seq = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                $sppdNumber = "{$seq}/SPPD/{$deptCode}/{$month}/{$year}";

                if (\App\Models\Sppd::where('sppd_number', $sppdNumber)->exists()) {
                    $sppdNumber = "{$seq}/SPPD/{$deptCode}/{$month}/{$year}-" . $employee->id;
                }

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
                    'sppd_number' => $sppdNumber,
                    'status' => 'draft',
                    'departure_date' => $startDate,
                    'departure_time' => '08:00',
                    'return_date' => $endDate,
                    'return_time' => '17:00',
                    'total_days' => $totalDays,
                    'origin_location' => $employee->area ?? 'Jakarta',
                    'destination_location' => $memo->location,
                    'assignment_purpose' => $memo->agenda ?? $memo->title,
                    'travel_category' => 'Dalam Negeri',
                    'general_travel_type' => 'Kunjungan Dinas Umum/Rapat Dinas/Dinas Survei *)',
                    'special_travel_type' => '≥ 80km dan menginap',
                    'transport_departure' => 'Sendiri',
                    'transport_arrival' => 'Kendaraan Dinas/Operasional',
                    'transportation_mode' => 'KA',
                    'destination_transportation' => ['Penugasan = Penginapan', 'Kendaraan Dinas/Operasional'],
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
