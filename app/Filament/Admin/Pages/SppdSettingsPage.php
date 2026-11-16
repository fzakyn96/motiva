<?php

namespace App\Filament\Admin\Pages;

use App\Models\SppdSetting;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SppdSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pengaturan SPPD';

    protected static ?string $title = 'Pengaturan Pengisian Formulir SPPD';

    protected static ?string $slug = 'sppd-settings';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.sppd-settings';

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
        $setting = SppdSetting::current();
        $this->form->fill([
            'form_filler_role' => $setting->form_filler_role,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Wewenang Pengisian Formulir SPPD')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('primary')
                    ->description('Tentukan pihak yang berwenang mengisi dan melengkapi rincian formulir SPPD ketika SPPD masih berstatus draf.')
                    ->schema([
                        Radio::make('form_filler_role')
                            ->label('Pengisi & Pelengkap Formulir SPPD')
                            ->options([
                                'HR' => 'HR (Default) — Formulir SPPD diisi dan dilengkapi oleh HR',
                                'Staff' => 'Staff — Pegawai (Staff) dapat mengisi dan melengkapi formulir SPPD miliknya saat status draf',
                            ])
                            ->helperText('Secara default, HR yang melengkapi seluruh isian formulir SPPD. Ubah ke Staff jika pegawai diperbolehkan melengkapi formulirnya sendiri.')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $role = in_array($state['form_filler_role'] ?? 'HR', ['HR', 'Staff'], true)
            ? $state['form_filler_role']
            : 'HR';

        SppdSetting::current()->update([
            'form_filler_role' => $role,
        ]);

        Notification::make()
            ->title('Pengaturan SPPD Disimpan')
            ->body("Pengisi formulir SPPD sekarang diatur ke: {$role}.")
            ->success()
            ->send();
    }
}

