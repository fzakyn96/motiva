<?php

namespace App\Filament\Admin\Pages;

use App\Models\OvertimeSetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OvertimeSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Pengaturan Lembur';

    protected static ?string $title = 'Pengaturan Kerja Lembur (SPKL)';

    protected static ?string $slug = 'overtime-settings';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.overtime-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = (\Filament\Facades\Filament::getCurrentPanel() ? \Filament\Facades\Filament::auth()->user() : null) ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public function mount(): void
    {
        $setting = OvertimeSetting::current();
        $this->form->fill([
            'allowed_creator_roles' => $setting->getAllowedRoles(),
            'fs_number' => $setting->fs_number ?: '05',
            'revision_number' => $setting->revision_number ?: '01',
            'max_daily_hours_weekday' => $setting->max_daily_hours_weekday ?: 4.0,
            'max_weekly_hours' => $setting->max_weekly_hours ?: 18.0,
            'compliance_mode' => $setting->compliance_mode ?: 'warning',
            'compliance_notes' => $setting->compliance_notes,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Wewenang Pembuatan Perintah Kerja Lembur (Bagian A)')
                    ->icon('heroicon-o-user-group')
                    ->iconColor('primary')
                    ->description('Tentukan peran yang berwenang membuat dan mengajukan Surat Perintah Kerja Lembur (SPKL).')
                    ->schema([
                        CheckboxList::make('allowed_creator_roles')
                            ->label('Peran yang Berwenang Membuat Perintah Lembur')
                            ->options([
                                'HR' => 'HR — Human Resources dapat membuat dan mengajukan SPKL',
                                'Manager' => 'Manajer — Manajer Area dapat membuat dan mengajukan SPKL',
                                'Supervisor' => 'Supervisor (SPV) — Supervisor dapat membuat dan mengajukan SPKL',
                            ])
                            ->required()
                            ->columns(1),
                    ]),

                Section::make('Identitas Template Formulir (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('info')
                    ->schema([
                        TextInput::make('fs_number')
                            ->label('FS No')
                            ->default('05')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('revision_number')
                            ->label('No Revisi')
                            ->default('01')
                            ->required()
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Section::make('Batas Jam Lembur & Kepatuhan Ketenagakerjaan (PP 35/2021)')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('warning')
                    ->description('Atur batas maksimal jam lembur sesuai PP No. 35 Tahun 2021 (sistem 5 hari kerja) serta mekanisme peringatan / catatan khusus.')
                    ->schema([
                        TextInput::make('max_daily_hours_weekday')
                            ->label('Batas Maksimal Harian (Hari Kerja N)')
                            ->numeric()
                            ->suffix('Jam / Hari')
                            ->default(4)
                            ->required(),
                        TextInput::make('max_weekly_hours')
                            ->label('Batas Maksimal Mingguan')
                            ->numeric()
                            ->suffix('Jam / Minggu')
                            ->default(18)
                            ->required(),
                        Select::make('compliance_mode')
                            ->label('Tindakan Saat Melebihi Batas')
                            ->options([
                                'warning' => 'Warning & Wajib Catatan Khusus (Tetap bisa diajukan dengan alasan darurat/backup)',
                                'block' => 'Hard Block (Tolak pengajuan & pertanggungjawaban yang melebihi batas)',
                            ])
                            ->default('warning')
                            ->required(),
                        Textarea::make('compliance_notes')
                            ->label('Catatan Khusus Kebijakan Lembur')
                            ->placeholder('Contoh: Kelebihan jam lembur hanya diperbolehkan untuk kondisi darurat operasional atau backup personel cuti dengan persetujuan Manajer.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $roles = array_values(array_intersect($state['allowed_creator_roles'] ?? [], ['HR', 'Manager', 'Supervisor']));
        if (empty($roles)) {
            $roles = ['HR', 'Manager', 'Supervisor'];
        }

        OvertimeSetting::current()->update([
            'allowed_creator_roles' => $roles,
            'fs_number' => $state['fs_number'] ?? '05',
            'revision_number' => $state['revision_number'] ?? '01',
            'max_daily_hours_weekday' => $state['max_daily_hours_weekday'] ?? 4,
            'max_weekly_hours' => $state['max_weekly_hours'] ?? 18,
            'compliance_mode' => $state['compliance_mode'] ?? 'warning',
            'compliance_notes' => $state['compliance_notes'] ?? null,
        ]);

        Notification::make()
            ->title('Pengaturan Lembur Disimpan')
            ->body('Wewenang pengajuan dan template formulir lembur berhasil diperbarui.')
            ->success()
            ->send();
    }
}
