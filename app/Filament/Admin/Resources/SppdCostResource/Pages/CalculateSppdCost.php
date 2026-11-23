<?php

namespace App\Filament\Admin\Resources\SppdCostResource\Pages;

use App\Filament\Admin\Resources\SppdCostResource;
use App\Models\Sppd;
use App\Models\SppdCost;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class CalculateSppdCost extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = SppdCostResource::class;

    protected static string $view = 'filament.pages.calculate-sppd-cost';

    protected static ?string $title = 'Hitung Biaya SPPD';

    public ?array $data = [];

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(SppdCostResource::getUrl('index')),
        ];
    }

    public function getMemoSiblingSppds(): \Illuminate\Support\Collection
    {
        if (! $this->record || ! $this->record->business_trip_id) {
            return collect([$this->record])->filter();
        }

        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $areaId = ($user && ! $user->hasAnyRole(['Superadmin', 'Admin'])) ? $user->employee?->work_area_id : null;

        return Sppd::with(['employee.position', 'cost', 'businessTrip.memo'])
            ->where('business_trip_id', $this->record->business_trip_id)
            ->where('status', 'approved')
            ->when($areaId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('work_area_id', $areaId)))
            ->orderBy('id')
            ->get();
    }

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing(['employee.position', 'employee.department', 'cost', 'businessTrip.memo']);

        $cost = $this->record->cost;

        if ($cost) {
            $formData = $cost->toArray();
        } else {
            $days = (int) $this->record->total_days;
            if ($days <= 0 && $this->record->departure_date && $this->record->return_date) {
                $days = \Carbon\Carbon::parse($this->record->departure_date)->diffInDays(\Carbon\Carbon::parse($this->record->return_date)) + 1;
            }
            $days = max(1, $days);

            $defaultBaseType = 'office_base_stay';
            $formData = SppdCost::getDefaultRates($defaultBaseType, $days);
            $formData['official_days'] = $days;
            $formData['base_type'] = $defaultBaseType;
            $formData['notes'] = null;
        }

        $siblings = $this->getMemoSiblingSppds();
        $siblingIds = $siblings->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $requestedIds = [];
        if (request()->filled('sppd_ids')) {
            $rawIds = explode(',', (string) request()->query('sppd_ids'));
            $requestedIds = array_values(array_intersect($siblingIds, array_map('intval', $rawIds)));
        }

        $formData['apply_mode'] = (request()->boolean('bulk_memo') && count($siblingIds) > 1)
            ? 'bulk_memo'
            : 'single';
        $formData['target_sppd_ids'] = ! empty($requestedIds) ? $requestedIds : $siblingIds;

        $this->form->fill($formData);
        $this->calculateFormTotals();
    }

    public function calculateFormTotals(): void
    {
        $raw = $this->data ?? [];

        // 1. Transport
        $tTerminal = (float) ($raw['transport_terminal_qty'] ?? 0) * (float) ($raw['transport_terminal_rate'] ?? 0);
        $tTicket = (float) ($raw['transport_ticket_qty'] ?? 0) * (float) ($raw['transport_ticket_rate'] ?? 0);
        $tLocal = (float) ($raw['transport_local_qty'] ?? 0) * (float) ($raw['transport_local_rate'] ?? 0);
        $subtotalTransport = $tTerminal + $tTicket + $tLocal;

        // 2. Harian
        $tHotel = (float) ($raw['hotel_qty'] ?? 0) * (float) ($raw['hotel_rate'] ?? 0);
        $tMeal = (float) ($raw['meal_qty'] ?? 0) * (float) ($raw['meal_rate'] ?? 0);
        $tMealReimburse = (float) ($raw['meal_reimbursement_qty'] ?? 0) * (float) ($raw['meal_reimbursement_rate'] ?? 0);
        $tTransit = (float) ($raw['local_transit_qty'] ?? 0) * (float) ($raw['local_transit_rate'] ?? 0);
        $tPocket = (float) ($raw['pocket_money_qty'] ?? 0) * (float) ($raw['pocket_money_rate'] ?? 0);
        $overseas = (float) ($raw['overseas_daily_allowance'] ?? 0) +
            (float) ($raw['overseas_hotel_meal'] ?? 0) +
            (float) ($raw['overseas_transport'] ?? 0) +
            (float) ($raw['overseas_pocket_money'] ?? 0) +
            (float) ($raw['overseas_monthly_allowance'] ?? 0);
        $subtotalPerDiem = $tHotel + $tMeal + $tMealReimburse + $tTransit + $tPocket + $overseas;

        // 3. Biaya Lain
        $subtotalOther = (float) ($raw['other_airport_tax'] ?? 0) +
            (float) ($raw['other_laundry'] ?? 0) +
            (float) ($raw['other_equipment'] ?? 0) +
            (float) ($raw['other_book_paper'] ?? 0) +
            (float) ($raw['other_assessor'] ?? 0) +
            (float) ($raw['other_car_rental'] ?? 0) +
            (float) ($raw['other_baggage'] ?? 0) +
            (float) ($raw['other_toll_fuel_parking'] ?? 0);

        $totalCost = $subtotalTransport + $subtotalPerDiem + $subtotalOther;

        $this->data['transport_terminal_total'] = $tTerminal;
        $this->data['transport_ticket_total'] = $tTicket;
        $this->data['transport_local_total'] = $tLocal;
        $this->data['subtotal_transport'] = $subtotalTransport;

        $this->data['hotel_total'] = $tHotel;
        $this->data['meal_total'] = $tMeal;
        $this->data['meal_reimbursement_total'] = $tMealReimburse;
        $this->data['local_transit_total'] = $tTransit;
        $this->data['pocket_money_total'] = $tPocket;
        $this->data['subtotal_per_diem'] = $subtotalPerDiem;

        $this->data['subtotal_other'] = $subtotalOther;
        $this->data['total_cost'] = $totalCost;
    }

    public function form(Form $form): Form
    {
        $siblings = $this->getMemoSiblingSppds();
        $schema = [];

        if ($siblings->count() > 1) {
            $memoNumber = $this->record->businessTrip?->memo?->nomor_memo ?: ('Memo #' . $this->record->business_trip_id);
            $empName = $this->record->employee?->name ?? 'Pegawai Ini';
            $sppdNum = $this->record->sppd_number ?? ('#' . $this->record->id);

            $siblingOptions = $siblings->mapWithKeys(function (Sppd $sppd): array {
                $name = e($sppd->employee?->name ?? 'Pegawai');
                $nip = $sppd->employee?->identity_number ? e($sppd->employee->identity_number) : null;
                $nameHtml = "<span class=\"font-semibold text-gray-900 dark:text-white\">{$name}</span>"
                    . ($nip ? " <span class=\"text-xs text-gray-500 dark:text-gray-400\">({$nip})</span>" : '');

                [$decisionLabel, $decisionStyle] = ! $sppd->cost
                    ? ['Belum Dihitung', 'background:rgba(156,163,175,0.15);color:#9ca3af;border:1px solid rgba(156,163,175,0.3);']
                    : match ($sppd->cost->status) {
                        'approved' => ['Disetujui Manajer', 'background:rgba(16,185,129,0.16);color:#10b981;border:1px solid rgba(16,185,129,0.35);'],
                        'pending' => ['Menunggu Manajer', 'background:rgba(245,158,11,0.16);color:#f59e0b;border:1px solid rgba(245,158,11,0.35);'],
                        'rejected' => ['Ditolak', 'background:rgba(239,68,68,0.16);color:#ef4444;border:1px solid rgba(239,68,68,0.35);'],
                        default => ['Draf', 'background:rgba(156,163,175,0.15);color:#9ca3af;border:1px solid rgba(156,163,175,0.3);'],
                    };

                $amountFormatted = $sppd->cost
                    ? 'Rp ' . number_format((float) $sppd->cost->total_cost, 0, ',', '.')
                    : 'Rp 0';
                $amountStyle = $sppd->cost
                    ? 'background:rgba(14,165,233,0.16);color:#38bdf8;border:1px solid rgba(14,165,233,0.35);font-weight:700;'
                    : 'background:rgba(107,114,128,0.12);color:#9ca3af;border:1px solid rgba(107,114,128,0.25);';

                $badgeBase = 'display:inline-flex;align-items:center;padding:0.125rem 0.5rem;border-radius:9999px;font-size:0.75rem;line-height:1.1rem;font-weight:600;';
                $decisionBadge = "<span style=\"{$badgeBase}{$decisionStyle}\">{$decisionLabel}</span>";
                $amountBadge = "<span style=\"{$badgeBase}{$amountStyle}\">{$amountFormatted}</span>";

                $labelHtml = "<span style=\"display:inline-flex;flex-wrap:wrap;align-items:center;gap:0.375rem;\">{$nameHtml} <span class=\"text-gray-400\">-</span> {$decisionBadge} <span class=\"text-gray-400\">-</span> {$amountBadge}</span>";

                return [(int) $sppd->id => $labelHtml];
            })->all();

            $schema[] = \Filament\Forms\Components\Section::make("Mode Pengisian Biaya Berdasarkan Nomor Memo ({$memoNumber})")
                ->icon('heroicon-o-squares-plus')
                ->iconColor('success')
                ->description("Memo ini memiliki {$siblings->count()} peserta penugasan. Anda dapat menerapkan rincian biaya dan evidence yang sama sekaligus (Bulk) berdasarkan Nomor Memo, atau mengisi khusus untuk 1 pegawai saja bilamana biaya/evidence-nya berbeda.")
                ->schema([
                    \Filament\Forms\Components\Radio::make('apply_mode')
                        ->label('Pilih Mode Pengisian Biaya & Evidence')
                        ->options([
                            'single' => "Individual — Hanya untuk {$empName} ({$sppdNum})",
                            'bulk_memo' => "Bulk Berdasarkan No. Memo ({$memoNumber}) — Terapkan Sekaligus ke Peserta Terpilih",
                        ])
                        ->descriptions([
                            'single' => 'Gunakan opsi ini bilamana rincian biaya atau evidence pegawai ini berbeda dari peserta lain.',
                            'bulk_memo' => 'Gunakan opsi ini apabila rincian biaya dan evidence sama untuk peserta penugasan dalam Nomor Memo ini.',
                        ])
                        ->default('single')
                        ->live()
                        ->required(),

                    \Filament\Forms\Components\CheckboxList::make('target_sppd_ids')
                        ->label('Daftar Peserta Penugasan dalam Nomor Memo Ini')
                        ->helperText('Centang pegawai yang rincian biaya dan evidence-nya sama. Hilangkan centang pada pegawai yang rincian biaya atau evidence-nya berbeda (pegawai tersebut dapat diisi tersendiri dengan mode Individual).')
                        ->options($siblingOptions)
                        ->allowHtml()
                        ->bulkToggleable()
                        ->columns(['default' => 1, 'md' => 2])
                        ->visible(fn (\Filament\Forms\Get $get): bool => $get('apply_mode') === 'bulk_memo')
                        ->required(fn (\Filament\Forms\Get $get): bool => $get('apply_mode') === 'bulk_memo'),
                ]);
        }

        return $form
            ->schema(array_merge($schema, SppdCostResource::getCostFormSchema()))
            ->statePath('data');
    }

    public function save(): void
    {
        $formData = $this->form->getState();
        $applyMode = $formData['apply_mode'] ?? 'single';
        $targetIds = is_array($formData['target_sppd_ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $formData['target_sppd_ids']))))
            : [];

        $costPayload = \Illuminate\Support\Arr::except($formData, ['apply_mode', 'target_sppd_ids']);
        $costPayload['creator_id'] = auth()->id();

        foreach (SppdCost::$numericFields as $field) {
            if (! isset($costPayload[$field]) || $costPayload[$field] === '' || $costPayload[$field] === null) {
                $costPayload[$field] = 0;
            }
        }

        if ($applyMode === 'bulk_memo' && ! empty($targetIds) && $this->record->business_trip_id) {
            $targetSppds = Sppd::where('business_trip_id', $this->record->business_trip_id)
                ->where('status', 'approved')
                ->whereIn('id', $targetIds)
                ->get();
        } else {
            $targetSppds = collect([$this->record]);
        }

        if ($targetSppds->isEmpty()) {
            $targetSppds = collect([$this->record]);
        }

        $lastCost = null;
        $requesterEmployee = auth()->user()?->employee;

        foreach ($targetSppds as $targetSppd) {
            $payload = $costPayload;
            $payload['sppd_id'] = $targetSppd->id;

            $cost = SppdCost::updateOrCreate(
                ['sppd_id' => $targetSppd->id],
                $payload
            );
            $cost->recalculateTotals();
            if ($cost->status !== 'approved') {
                $cost->status = 'pending';
            }
            $cost->save();

            if ($cost->status === 'pending') {
                $cost->ensureApprovalRequestExists($requesterEmployee);
            }

            $lastCost = $cost;
        }

        if ($targetSppds->count() > 1) {
            $memoNumber = $this->record->businessTrip?->memo?->nomor_memo ?: ('Memo #' . $this->record->business_trip_id);
            Notification::make()
                ->title('Perhitungan Biaya SPPD Massal (Bulk) Disimpan')
                ->body("Rincian biaya (Rp " . number_format((float) ($lastCost?->total_cost ?? 0), 0, ',', '.') . " / orang) beserta evidence berhasil diterapkan ke {$targetSppds->count()} peserta penugasan pada No. Memo {$memoNumber} dan diajukan ke Manajer.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Perhitungan Biaya SPPD Disimpan & Diajukan ke Manajer')
                ->body('Total biaya sebesar Rp ' . number_format((float) ($lastCost?->total_cost ?? 0), 0, ',', '.') . ' telah diajukan untuk persetujuan Manajer.')
                ->success()
                ->send();
        }

        $this->redirect(SppdCostResource::getUrl('index'));
    }
}
