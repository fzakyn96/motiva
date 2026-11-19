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

    protected static ?string $title = 'Pengaturan Management Fee';

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
            'transport_manfee_percent' => $setting->transport_manfee_percent,
            'travel_manfee_percent' => $setting->travel_manfee_percent,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Persentase Management Fee (Manfee)')
                    ->icon('heroicon-o-percent-badge')
                    ->iconColor('primary')
                    ->description('Atur batas persentase tertinggi (maksimal) Management Fee untuk Rekap Tiket & Akomodasi serta Rekap Perjalanan Dinas. Pada halaman rekap, nilai ini menjadi nilai default dan masih dapat disesuaikan lebih kecil, namun tidak boleh melebihi batas ini.')
                    ->schema([
                        TextInput::make('transport_manfee_percent')
                            ->label('Batas Maksimal Management Fee Tiket & Akomodasi (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Persentase tertinggi (default 5%) dari Total Biaya pada Rekap Tiket & Akomodasi.')
                            ->required(),
                        TextInput::make('travel_manfee_percent')
                            ->label('Batas Maksimal Management Fee Perjalanan Dinas (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Persentase tertinggi (default 6%) dari Total Biaya Pekerjaan pada Rekap Perjalanan Dinas.')
                            ->required(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        ReimbursementSetting::current()->update([
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

