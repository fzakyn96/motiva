<?php

namespace App\Services\Approval;

use App\Filament\Admin\Resources\MemoApprovalResource;
use App\Filament\Admin\Resources\OfficialTravelApprovalResource;
use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Resources\SppdCostApprovalResource;
use App\Filament\Admin\Resources\TransportAccommodationApprovalResource;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\Reimbursement;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
use App\Models\User;
use App\Notifications\ApprovalStatusNotification;
use App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver;
use Illuminate\Support\Facades\Log;

class ApprovalNotificationService
{
    /**
     * Queue formal email notifications to active authorized approvers (excluding Contract Manager / BDH).
     */
    public function notifyPendingApprovers(ApprovalRequest $request): void
    {
        try {
            $request->refresh();
            $currentStep = $request->currentStep();

            if ($request->status !== 'pending' || ! $currentStep) {
                return;
            }

            // Do not send email notifications to Contract Manager / Business Division Head (BDH)
            if ($currentStep->policyStep?->resolver_class === BusinessDivisionHeadAuthorityResolver::class) {
                return;
            }

            [$typeLabel, $title, $actionUrl, $details] = $this->resolveDocumentSummary($request);
            $requesterName = $request->requester?->name ?? 'Pemohon';
            $resolver = new ApprovalResolver();

            $candidates = Employee::with('user')
                ->whereNotNull('user_id')
                ->get();

            $notifiedUserIds = [];

            foreach ($candidates as $employee) {
                if ($employee->isBusinessDivisionHead() || $employee->isContractManager()) {
                    continue;
                }

                $user = $employee->user;
                if (! $this->isNotifiableUser($user, $notifiedUserIds)) {
                    continue;
                }

                // Skip Superadmin/Admin who are not actual structural approvers for the current step
                if ($user->hasAnyRole(['Superadmin', 'Admin']) && ! $user->hasAnyRole(['Supervisor', 'Manager', 'Division Head'])) {
                    continue;
                }

                if (! $resolver->canApprove($request, $employee)) {
                    continue;
                }

                $notifiedUserIds[] = $user->id;
                $user->notify(new ApprovalStatusNotification(
                    event: 'submitted',
                    documentTypeLabel: $typeLabel,
                    documentTitle: $title,
                    requesterName: $requesterName,
                    notes: null,
                    actionUrl: $actionUrl,
                    details: $details,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue approval pending notification: ' . $e->getMessage(), [
                'approval_request_id' => $request->id,
            ]);
        }
    }

    /**
     * Queue formal email notifications only to the requester and assigned employees on approval/rejection.
     */
    public function notifyRequesterResult(ApprovalRequest $request, string $event, ?string $notes = null): void
    {
        try {
            [$typeLabel, $title, $actionUrl, $details] = $this->resolveDocumentSummary($request);
            $requesterEmployee = $request->requester;
            $requesterName = $requesterEmployee?->name ?? 'Pemohon';
            $notifiedUserIds = [];

            if ($requesterEmployee && ! $requesterEmployee->isBusinessDivisionHead() && ! $requesterEmployee->isContractManager()) {
                $requesterUser = $requesterEmployee->user;
                if ($this->isNotifiableUser($requesterUser, $notifiedUserIds)) {
                    $notifiedUserIds[] = $requesterUser->id;
                    $requesterUser->notify(new ApprovalStatusNotification(
                        event: $event,
                        documentTypeLabel: $typeLabel,
                        documentTitle: $title,
                        requesterName: $requesterName,
                        notes: $notes,
                        actionUrl: $actionUrl,
                        details: $details,
                    ));
                }
            }

            if ($event === 'approved') {
                foreach ($this->resolveAssignedUsers($request) as $assignedUser) {
                    if (! $this->isNotifiableUser($assignedUser, $notifiedUserIds)) {
                        continue;
                    }

                    $notifiedUserIds[] = $assignedUser->id;
                    $assignedUser->notify(new ApprovalStatusNotification(
                        event: 'assigned',
                        documentTypeLabel: $typeLabel,
                        documentTitle: $title,
                        requesterName: $requesterName,
                        notes: null,
                        actionUrl: $actionUrl,
                        details: $details,
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue approval result notification: ' . $e->getMessage(), [
                'approval_request_id' => $request->id,
                'event' => $event,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $notifiedUserIds
     */
    protected function isNotifiableUser(?User $user, array $notifiedUserIds): bool
    {
        if (! $user || in_array($user->id, $notifiedUserIds, true)) {
            return false;
        }

        return (bool) filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Resolve employees directly assigned to the document on the approval flow.
     *
     * @return array<int, User>
     */
    protected function resolveAssignedUsers(ApprovalRequest $request): array
    {
        $doc = $request->document;
        $users = [];

        if ($doc instanceof SppdCost) {
            $employee = $doc->sppd?->employee;
            if ($employee && ! $employee->isBusinessDivisionHead() && ! $employee->isContractManager() && $employee->user) {
                $users[] = $employee->user;
            }
        }

        return $users;
    }

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: array<string, string>}
     */
    protected function resolveDocumentSummary(ApprovalRequest $request): array
    {
        $doc = $request->document;
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($doc instanceof Memo) {
            $participantCount = is_array($doc->participants) ? count($doc->participants) : 0;

            return [
                'Memo',
                (string) ($doc->nomor_memo ?? ''),
                $this->safeResourceUrl(fn () => MemoApprovalResource::getUrl('index'), $baseUrl),
                array_filter([
                    'Agenda' => $doc->agenda ?: $doc->title,
                    'Tanggal Memo' => $doc->date?->format('d/m/Y'),
                    'Jumlah Peserta' => $participantCount > 0 ? "{$participantCount} orang" : null,
                ]),
            ];
        }

        if ($doc instanceof Sppd) {
            $sppdGroup = $doc->business_trip_id
                ? Sppd::with('employee')->where('business_trip_id', $doc->business_trip_id)->get()
                : collect([$doc->loadMissing('employee')]);

            $employeeNames = $sppdGroup
                ->map(fn (Sppd $item) => $item->employee?->name)
                ->filter()
                ->unique()
                ->values();

            $dateRange = ($doc->departure_date && $doc->return_date)
                ? $doc->departure_date->format('d/m/Y') . ' s.d. ' . $doc->return_date->format('d/m/Y')
                : null;

            return [
                'SPPD',
                (string) ($doc->sppd_number ?? ''),
                $this->safeResourceUrl(fn () => SppdApprovalResource::getUrl('index'), $baseUrl),
                array_filter([
                    'Tujuan Perjalanan' => $doc->destination_location,
                    'Jadwal Perjalanan' => $dateRange,
                    'Jumlah Pegawai' => $employeeNames->count() . ' orang',
                    'Nama Pegawai' => $employeeNames->implode(', '),
                ]),
            ];
        }

        if ($doc instanceof SppdCost) {
            $doc->loadMissing('sppd.employee', 'sppd.businessTrip.memo');
            $sppd = $doc->sppd;
            $memo = $sppd?->businessTrip?->memo;
            $agenda = $memo?->agenda ?: ($memo?->title ?: $sppd?->businessTrip?->activity_name);
            $dateRange = ($sppd?->departure_date && $sppd?->return_date)
                ? $sppd->departure_date->format('d/m/Y') . ' s.d. ' . $sppd->return_date->format('d/m/Y')
                : null;

            return [
                'Biaya SPPD',
                (string) ($sppd?->sppd_number ?? ''),
                $this->safeResourceUrl(fn () => SppdCostApprovalResource::getUrl('index'), $baseUrl),
                array_filter([
                    'Agenda' => $agenda,
                    'Pegawai' => $sppd?->employee?->name,
                    'Tujuan Perjalanan' => $sppd?->destination_location,
                    'Jadwal Perjalanan' => $dateRange,
                    'Total Biaya' => 'Rp ' . number_format((float) $doc->grand_total, 0, ',', '.'),
                ]),
            ];
        }

        if ($doc instanceof TransportAccommodationRecap) {
            return [
                'Rekap Tiket & Akomodasi',
                (string) ($doc->recap_number ?? ''),
                $this->safeResourceUrl(fn () => TransportAccommodationApprovalResource::getUrl('index'), $baseUrl),
                array_filter([
                    'Nama Pekerjaan' => $doc->work_name,
                    'Total Tagihan' => 'Rp ' . number_format((float) $doc->total_amount, 0, ',', '.'),
                ]),
            ];
        }

        if ($doc instanceof OfficialTravelRecap) {
            return [
                'Rekap Perjalanan Dinas',
                (string) ($doc->recap_number ?? ''),
                $this->safeResourceUrl(fn () => OfficialTravelApprovalResource::getUrl('index'), $baseUrl),
                array_filter([
                    'Nama Pekerjaan' => $doc->work_name,
                    'Total Tagihan' => 'Rp ' . number_format((float) $doc->total_amount, 0, ',', '.'),
                ]),
            ];
        }

        if ($doc instanceof Reimbursement) {
            return [
                'Reimbursement',
                (string) ($doc->reimbursement_number ?? ''),
                $baseUrl ?: null,
                [],
            ];
        }

        return [
            'Dokumen',
            "#{$request->document_id}",
            $baseUrl ?: null,
            [],
        ];
    }

    protected function safeResourceUrl(callable $resolver, string $fallback): ?string
    {
        try {
            return $resolver();
        } catch (\Throwable) {
            return $fallback ?: null;
        }
    }
}
