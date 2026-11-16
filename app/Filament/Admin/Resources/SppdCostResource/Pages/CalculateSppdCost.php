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

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

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
        return $form
            ->schema(SppdCostResource::getCostFormSchema())
            ->statePath('data');
    }

    public function save(): void
    {
        $formData = $this->form->getState();
        $formData['sppd_id'] = $this->record->id;
        $formData['creator_id'] = auth()->id();

        foreach (SppdCost::$numericFields as $field) {
            if (! isset($formData[$field]) || $formData[$field] === '' || $formData[$field] === null) {
                $formData[$field] = 0;
            }
        }

        $cost = SppdCost::updateOrCreate(
            ['sppd_id' => $this->record->id],
            $formData
        );
        $cost->recalculateTotals();
        if ($cost->status !== 'approved') {
            $cost->status = 'pending';
        }
        $cost->save();

        if ($cost->status === 'pending') {
            $cost->ensureApprovalRequestExists(auth()->user()?->employee);
        }

        Notification::make()
            ->title('Perhitungan Biaya SPPD Disimpan & Diajukan ke Manajer')
            ->body('Total biaya sebesar Rp ' . number_format($cost->total_cost, 0, ',', '.') . ' telah diajukan untuk persetujuan Manajer.')
            ->success()
            ->send();

        $this->redirect(SppdCostResource::getUrl('index'));
    }
}
