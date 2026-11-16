<?php

namespace App\Filament\Admin\Pages;

use App\Models\ReimbursementSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReimbursementSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Pengaturan Fee Rekap';

    protected static ?string $title = 'Pengaturan Service Fee & Management Fee';

    protected static ?string $slug = 'reimbursement-settings';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reimbursement-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public function mount(): void
    {
        $setting = ReimbursementSetting::current();
        $this->form->fill([
            'transport_service_fee_percent' => $setting->transport_service_fee_percent,
            'transport_manfee_percent' => $setting->transport_manfee_percent,
            'travel_manfee_percent' => $setting->travel_manfee_percent,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Persentase Service Fee & Management Fee (Manfee)')
                    ->icon('heroicon-o-percent-badge')
                    ->iconColor('primary')
                    ->description('Atur persentase default Service Fee dan Management Fee untuk Rekapitulasi Biaya Reimbursement Transportasi & Akomodasi serta Perjalanan Dinas.')
                    ->schema([
                        TextInput::make('transport_service_fee_percent')
                            ->label('Service Fee Transportasi & Akomodasi (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Jika > 0%, nominal Service Fee per item akan dihitung otomatis dari Jumlah × persentase ini (tetap dapat disesuaikan manual per item). Isi 0 jika Service Fee diinput manual per item.')
                            ->required(),
                        TextInput::make('transport_manfee_percent')
                            ->label('Manfee Transportasi & Akomodasi (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Default 5% dari Total Biaya pada Rekapitulasi Biaya Reimbursement Transportasi dan Akomodasi.')
                            ->required(),
                        TextInput::make('travel_manfee_percent')
                            ->label('Management Fee Perjalanan Dinas (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Default 6% dari Total Biaya Pekerjaan pada Rekapitulasi Biaya Reimbursement Perjalanan Dinas.')
                            ->required(),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        ReimbursementSetting::current()->update([
            'transport_service_fee_percent' => (float) ($state['transport_service_fee_percent'] ?? 0),
            'transport_manfee_percent' => (float) ($state['transport_manfee_percent'] ?? 5),
            'travel_manfee_percent' => (float) ($state['travel_manfee_percent'] ?? 6),
        ]);

        Notification::make()
            ->title('Pengaturan Fee Disimpan')
            ->body('Persentase Service Fee dan Management Fee berhasil diperbarui.')
            ->success()
            ->send();
    }
}

