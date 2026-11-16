<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdResource\Pages;
use App\Models\Sppd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SppdResource extends Resource
{
    protected static ?string $model = Sppd::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationGroup = 'Dokumen';
    protected static ?string $navigationLabel = 'SPPD';
    protected static ?string $modelLabel = 'SPPD';
    protected static ?string $pluralModelLabel = 'SPPD';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // Managers and Supervisors only need "Persetujuan SPPD", not the master "SPPD" menu
        if ($user->hasAnyRole(['Manager', 'Supervisor']) && ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return false;
        }

        return true;
    }

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['Manager', 'Supervisor']) && ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Staff']);
    }

    public static function canCreate(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Supervisor'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return false;
        }

        return (int) $record->employee_id === (int) $employee->id;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // HR dan Superadmin boleh mengedit SPPD
        if ($user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return false;
        }

        // Staff hanya bisa mengedit SPPD miliknya jika status masih draft DAN pengaturan mengizinkan Staff mengisi form SPPD
        return (int) $record->employee_id === (int) $employee->id
            && $record->status === 'draft'
            && \App\Models\SppdSetting::current()->isStaffFiller();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // HR dan Staff tidak boleh menghapus SPPD
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public static function canDeleteAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Dokumen SPPD';
    }

    public static function getNavigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(function () {
                    if (request()->routeIs('filament.admin.resources.sppds.review') && (request()->query('from') === 'approvals' || auth()->user()?->hasAnyRole(['Manager', 'Supervisor']))) {
                        return false;
                    }
                    return request()->routeIs(static::getRouteBaseName() . '.*');
                })
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query;
        }

        if ($user->hasAnyRole(['Manager', 'Supervisor', 'HR'])) {
            return $query->where(function (Builder $sub) use ($employee) {
                $sub->whereHas('employee', function ($q) use ($employee) {
                    $q->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                })
                ->orWhere('status', '!=', 'draft');
            });
        }

        return $query->where('employee_id', $employee->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pekerja & Penugasan')
                    ->icon('heroicon-o-identification')
                    ->iconColor('primary')
                    ->description('Lengkapi identitas pekerja yang diperintah serta lokasi dan tujuan perjalanan dinas.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('Data Pekerja & Nomor Dokumen')
                            ->schema([
                                Forms\Components\Select::make('business_trip_id')
                                    ->relationship('businessTrip', 'activity_name')
                                    ->label('Kegiatan Perjalanan Dinas')
                                    ->prefixIcon('heroicon-m-briefcase')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (string $context) => $context === 'create'),
                                Forms\Components\Select::make('employee_id')
                                    ->relationship('employee', 'name')
                                    ->label('Pegawai yang Ditugaskan')
                                    ->prefixIcon('heroicon-m-user')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (string $context) => $context === 'create'),
                                Forms\Components\TextInput::make('employee_name')
                                    ->label('1. Nama Pekerja yang Diperintah')
                                    ->prefixIcon('heroicon-m-user')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (string $context) => $context !== 'create')
                                    ->formatStateUsing(fn ($record) => $record?->employee?->name ?? auth()->user()?->employee?->name),
                                Forms\Components\TextInput::make('travel_grade')
                                    ->label('2. Golongan Perjalanan Dinas')
                                    ->prefixIcon('heroicon-m-academic-cap')
                                    ->placeholder('Kosongkan bila tidak ada'),
                                Forms\Components\TextInput::make('position_name')
                                    ->label('3. Jabatan')
                                    ->prefixIcon('heroicon-m-briefcase')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($record) => $record?->employee?->position?->name ?? '-'),
                                Forms\Components\TextInput::make('sppd_number')
                                    ->label('Nomor SPPD')
                                    ->prefixIcon('heroicon-m-hashtag')
                                    ->placeholder('Nomor dokumen belum terbit')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),

                        Forms\Components\Fieldset::make('Rute Lokasi & Maksud Penugasan')
                            ->schema([
                                Forms\Components\TextInput::make('origin_location')
                                    ->label('6. Tempat Kedudukan (Kota Asal)')
                                    ->prefixIcon('heroicon-m-building-office-2')
                                    ->default(fn (?Sppd $record) => $record?->origin_location ?? ($record?->employee?->area ?? (auth()->user()?->employee?->area ?? 'Jakarta')))
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tempat kedudukan (kota asal) wajib diisi.',
                                    ]),
                                Forms\Components\TextInput::make('destination_location')
                                    ->label('7. Lokasi Penugasan (Kota Tujuan)')
                                    ->prefixIcon('heroicon-m-map-pin')
                                    ->placeholder('Contoh: PGN Distribution Pipeline - Area Operasi Lampung')
                                    ->default(fn (?Sppd $record) => $record?->destination_location ?? ($record?->businessTrip?->memo?->location ?? ($record?->businessTrip?->destination ?? '')))
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Lokasi penugasan (kota tujuan) wajib diisi.',
                                    ]),
                                Forms\Components\Textarea::make('assignment_purpose')
                                    ->label('8. Deskripsi Tujuan Penugasan')
                                    ->helperText('Jabarkan peran, tanggung jawab, dan tugas pada pelaksanaan perjalanan dinas.')
                                    ->placeholder('Contoh: Sinkronisasi Data Aset DIGIO - I NICO')
                                    ->default(fn (?Sppd $record) => $record?->assignment_purpose ?? ($record?->businessTrip?->memo?->agenda ?? ($record?->businessTrip?->memo?->title ?? ($record?->businessTrip?->activity_name ?? ''))))
                                    ->rows(3)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Deskripsi tujuan penugasan wajib diisi.',
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Jadwal Perjalanan Dinas')
                    ->icon('heroicon-o-calendar-days')
                    ->iconColor('info')
                    ->description('Tentukan tanggal dan waktu keberangkatan, kepulangan, serta total durasi hari dinas.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\DatePicker::make('departure_date')
                            ->label('4. Tanggal Berangkat')
                            ->prefixIcon('heroicon-m-calendar')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->closeOnDateSelection()
                            ->default(fn (?Sppd $record) => ($d = $record?->departure_date ?? ($record?->businessTrip?->memo?->assignment_start_date ?? $record?->businessTrip?->start_date)) ? \Carbon\Carbon::parse($d)->format('Y-m-d') : null)
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal berangkat wajib diisi.',
                            ])
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state && $get('return_date')) {
                                    $start = \Carbon\Carbon::parse($state);
                                    $end = \Carbon\Carbon::parse($get('return_date'));
                                    if ($end->greaterThanOrEqualTo($start)) {
                                        $set('total_days', $start->diffInDays($end) + 1);
                                    }
                                }
                            }),
                        Forms\Components\TimePicker::make('departure_time')
                            ->label('Jam Berangkat')
                            ->prefixIcon('heroicon-m-clock')
                            ->default(fn (?Sppd $record) => $record?->departure_time ?? '08:00')
                            ->seconds(false)
                            ->required()
                            ->validationMessages([
                                'required' => 'Jam berangkat wajib diisi.',
                            ]),
                        Forms\Components\DatePicker::make('return_date')
                            ->label('5. Tanggal Kembali')
                            ->prefixIcon('heroicon-m-calendar')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->closeOnDateSelection()
                            ->default(fn (?Sppd $record) => ($d = $record?->return_date ?? ($record?->businessTrip?->memo?->assignment_end_date ?? $record?->businessTrip?->end_date)) ? \Carbon\Carbon::parse($d)->format('Y-m-d') : null)
                            ->afterOrEqual('departure_date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal kembali wajib diisi.',
                                'after_or_equal' => 'Tanggal kembali tidak boleh lebih awal dari tanggal berangkat.',
                            ])
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state && $get('departure_date')) {
                                    $start = \Carbon\Carbon::parse($get('departure_date'));
                                    $end = \Carbon\Carbon::parse($state);
                                    if ($end->greaterThanOrEqualTo($start)) {
                                        $set('total_days', $start->diffInDays($end) + 1);
                                    }
                                }
                            }),
                        Forms\Components\TimePicker::make('return_time')
                            ->label('Jam Kembali')
                            ->prefixIcon('heroicon-m-clock')
                            ->default(fn (?Sppd $record) => $record?->return_time ?? '17:00')
                            ->seconds(false)
                            ->required()
                            ->validationMessages([
                                'required' => 'Jam kembali wajib diisi.',
                            ]),
                        Forms\Components\TextInput::make('total_days')
                            ->label('Total Durasi Dinas')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(function (?Sppd $record) {
                                if ($record?->total_days) {
                                    return $record->total_days;
                                }
                                $start = $record?->departure_date ?? ($record?->businessTrip?->memo?->assignment_start_date ?? $record?->businessTrip?->start_date);
                                $end = $record?->return_date ?? ($record?->businessTrip?->memo?->assignment_end_date ?? $record?->businessTrip?->end_date);
                                if ($start && $end) {
                                    return \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
                                }
                                return 1;
                            })
                            ->suffix('Hari')
                            ->helperText('Dihitung otomatis dari rentang tanggal.')
                            ->required()
                            ->validationMessages([
                                'required' => 'Total durasi dinas wajib diisi.',
                                'min' => 'Total durasi dinas minimal 1 hari.',
                            ])
                            ->readOnly(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 5]),

                Forms\Components\Section::make('Klasifikasi & Kategori Perjalanan Dinas')
                    ->icon('heroicon-o-tag')
                    ->iconColor('warning')
                    ->description('Pilih cakupan wilayah serta klasifikasi perjalanan dinas umum dan khusus.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('9. Kategori Wilayah Perjalanan Dinas')
                            ->schema([
                                Forms\Components\Radio::make('travel_category')
                                    ->label('Pilih Kategori Wilayah')
                                    ->options([
                                        'Dalam Negeri' => 'Dalam Negeri',
                                        'Khusus' => 'Khusus',
                                        'Luar Negeri' => 'Luar Negeri',
                                    ])
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Kategori wilayah perjalanan dinas (poin 9) wajib dipilih.',
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Fieldset::make('10. Jenis Perjalanan Dinas Umum')
                                    ->schema([
                                        Forms\Components\Radio::make('general_travel_type')
                                            ->label('Pilih Jenis Perjalanan Dinas Umum')
                                            ->options([
                                                'Kunjungan Dinas Umum/Rapat Dinas/Dinas Survei' => 'Kunjungan Dinas Umum / Rapat Dinas / Dinas Survei',
                                                'Pemindahan Permanen/Penugasan Sementara' => 'Pemindahan Permanen / Penugasan Sementara',
                                                'Pendidikan dan Pelatihan' => 'Pendidikan dan Pelatihan',
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Jenis perjalanan dinas umum (poin 10) wajib dipilih.',
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpan(1),

                                Forms\Components\Fieldset::make('11. Jenis Perjalanan Dinas Khusus')
                                    ->schema([
                                        Forms\Components\Radio::make('special_travel_type')
                                            ->label('Pilih Jenis Perjalanan Dinas Khusus')
                                            ->options([
                                                '≥ 80km dan menginap' => '≥ 80 km dan menginap',
                                                '≥ 80km dan tidak menginap' => '≥ 80 km dan tidak menginap',
                                                '≤ 80km dan menginap' => '≤ 80 km dan menginap',
                                                '≤ 80 km dan tidak menginap' => '≤ 80 km dan tidak menginap',
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Jenis perjalanan dinas khusus (poin 11) wajib dipilih.',
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ]),

                Forms\Components\Section::make('Transportasi & Fasilitas')
                    ->icon('heroicon-o-truck')
                    ->iconColor('success')
                    ->description('Pilih moda transportasi menuju bandara/stasiun/terminal, sarana utama, dan transportasi di tempat tujuan.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('Transportasi Lokal Keberangkatan & Kedatangan')
                            ->schema([
                                Forms\Components\Radio::make('transport_departure')
                                    ->label('12. Transportasi ke Bandara/Stasiun/Terminal (Keberangkatan)')
                                    ->options([
                                        'Sendiri' => 'Sendiri',
                                        'Kendaraan Dinas/Operasional' => 'Kendaraan Dinas / Operasional',
                                    ])
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Transportasi keberangkatan (poin 12) wajib dipilih.',
                                    ]),
                                Forms\Components\Radio::make('transport_arrival')
                                    ->label('13. Transportasi dari Bandara/Stasiun/Terminal (Kedatangan)')
                                    ->options([
                                        'Sendiri' => 'Sendiri',
                                        'Kendaraan Dinas/Operasional' => 'Kendaraan Dinas / Operasional',
                                    ])
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Transportasi kedatangan (poin 13) wajib dipilih.',
                                    ]),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),

                        Forms\Components\Fieldset::make('Sarana Utama & Transportasi di Lokasi Tujuan')
                            ->schema([
                                Forms\Components\Radio::make('transportation_mode')
                                    ->label('14. Sarana Transportasi Utama')
                                    ->options([
                                        'PU' => 'PU — Pesawat Udara',
                                        'KA' => 'KA — Kereta Api',
                                        'TUD' => 'TUD — Transportasi Umum Darat',
                                        'KL' => 'KL — Kapal Laut',
                                    ])
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Sarana transportasi utama (poin 14) wajib dipilih.',
                                    ]),
                                Forms\Components\CheckboxList::make('destination_transportation')
                                    ->label('15. Sarana Transportasi di Tujuan')
                                    ->helperText('Pilih minimal satu sarana transportasi di lokasi tujuan.')
                                    ->options([
                                        'Penugasan = Penginapan' => 'Penugasan = Penginapan',
                                        'Kendaraan Dinas/Operasional' => 'Kendaraan Dinas / Operasional',
                                        'Mengatur Sendiri' => 'Mengatur Sendiri',
                                        'Sewa Kendaraan' => 'Sewa Kendaraan',
                                    ])
                                    ->columns(2)
                                    ->required()
                                    ->minItems(1)
                                    ->validationMessages([
                                        'required' => 'Sarana transportasi di tujuan (poin 15) wajib dipilih minimal 1.',
                                        'min' => 'Sarana transportasi di tujuan (poin 15) wajib dipilih minimal 1.',
                                    ]),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status Dokumen & Jejak Persetujuan')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\ViewEntry::make('approval_trail')
                            ->view('filament.infolists.sppd-approval-trail')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('1. Identitas Pegawai yang Diperintah')
                    ->icon('heroicon-o-user')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Nama Pegawai')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('employee.identity_number')
                            ->label('NIP / Nomor Induk Pegawai'),
                        Infolists\Components\TextEntry::make('employee.position.name')
                            ->label('Jabatan'),
                        Infolists\Components\TextEntry::make('travel_grade')
                            ->label('Golongan Perjalanan Dinas')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('sppd_number')
                            ->label('Nomor SPPD')
                            ->badge()
                            ->color('gray')
                            ->placeholder('Nomor dokumen belum terbit'),
                        Infolists\Components\TextEntry::make('origin_location')
                            ->label('Tempat Kedudukan')
                            ->icon('heroicon-m-map-pin'),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('2. Penugasan & Lokasi')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('info')
                    ->schema([
                        Infolists\Components\TextEntry::make('destination_location')
                            ->label('Lokasi Penugasan')
                            ->icon('heroicon-m-map-pin')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('assignment_purpose')
                            ->label('Deskripsi Tujuan Penugasan')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2]),

                Infolists\Components\Section::make('3. Waktu & Durasi Perjalanan Dinas')
                    ->icon('heroicon-o-calendar-days')
                    ->iconColor('warning')
                    ->schema([
                        Infolists\Components\TextEntry::make('departure_date')
                            ->label('Tanggal Berangkat')
                            ->date('d F Y')
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('departure_time')
                            ->label('Waktu Keberangkatan')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('return_date')
                            ->label('Tanggal Kembali')
                            ->date('d F Y')
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('return_time')
                            ->label('Waktu Kepulangan')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('total_days')
                            ->label('Lama Hari Penugasan')
                            ->formatStateUsing(fn ($state) => $state ? "{$state} Hari" : '-')
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(['default' => 2, 'sm' => 5]),

                Infolists\Components\Section::make('4. Transportasi & Ketentuan Pelaksanaan')
                    ->icon('heroicon-o-truck')
                    ->iconColor('success')
                    ->schema([
                        Infolists\Components\TextEntry::make('travel_category')
                            ->label('Kategori Perjalanan')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('general_travel_type')
                            ->label('Jenis Perjalanan Dinas')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('transportation_mode')
                            ->label('Moda Transportasi')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('transport_departure')
                            ->label('Biaya Transport Berangkat')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('transport_arrival')
                            ->label('Biaya Transport Kembali')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('destination_transportation')
                            ->label('Transportasi di Tempat Tujuan')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : ($state ?: '-'))
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('agenda')
                        ->label('Agenda')
                        ->state(fn (Sppd $record) => $record->assignment_purpose ?: ($record->businessTrip?->memo?->agenda ?: ($record->businessTrip?->activity_name ?: '-')))
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->where('assignment_purpose', 'like', "%{$search}%")
                                ->orWhereHas('businessTrip', fn ($q) => $q->where('activity_name', 'like', "%{$search}%")
                                    ->orWhereHas('memo', fn ($mq) => $mq->where('agenda', 'like', "%{$search}%")));
                        })
                        ->wrap()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('approval_stage_label')
                        ->label('Status')
                        ->badge()
                        ->grow(false)
                        ->state(fn (Sppd $record) => $record->approval_stage_label)
                        ->color(fn (string $state): string => match ($state) {
                            'Draf' => 'gray',
                            'Menunggu Supervisor' => 'warning',
                            'Menunggu Manajer (Disetujui SPV)' => 'info',
                            'Disetujui' => 'success',
                            'Ditolak' => 'danger',
                            default => 'warning',
                        }),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('employee.name')
                                ->description('Pegawai yang Ditugaskan', position: 'above')
                                ->weight('medium')
                                ->formatStateUsing(function (Sppd $record) {
                                    $emp = $record->employee;
                                    if (! $emp) return '-';
                                    $pos = $emp->position?->name;
                                    return $pos ? "{$emp->name} ({$pos})" : $emp->name;
                                }),
                            Tables\Columns\TextColumn::make('period')
                                ->description('Jadwal Dinas', position: 'above')
                                ->state(function (Sppd $record) {
                                    $start = $record->departure_date ?? ($record->businessTrip?->memo?->assignment_start_date ?? $record->businessTrip?->start_date);
                                    $end = $record->return_date ?? ($record->businessTrip?->memo?->assignment_end_date ?? $record->businessTrip?->end_date);
                                    $startFormatted = $start ? \Carbon\Carbon::parse($start)->format('d M Y') : '-';
                                    $endFormatted = $end ? \Carbon\Carbon::parse($end)->format('d M Y') : '-';
                                    $days = $record->total_days;
                                    if (! $days && $start && $end) {
                                        $days = \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
                                    }
                                    $daysStr = $days ? " ({$days} Hari)" : '';
                                    return "{$startFormatted} s/d {$endFormatted}{$daysStr}";
                                }),
                            Tables\Columns\TextColumn::make('destination_location')
                                ->description('Lokasi Penugasan', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('sppd_number')
                                ->description('Nomor SPPD', position: 'above')
                                ->placeholder('Nomor dokumen belum terbit'),
                            Tables\Columns\TextColumn::make('travel_category')
                                ->description('Kategori & Sarana Transportasi', position: 'above')
                                ->state(fn (Sppd $record) => ($record->travel_category ?: 'Dalam Negeri') . ' • ' . ($record->transportation_mode ?: '-')),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Dibuat Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                \App\Filament\Admin\Support\RecapTableSupport::monthYearFilter('departure_date'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draf',
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label(fn (Sppd $record) => $record->status === 'pending' && auth()->user()?->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin']) ? 'Review' : 'Lihat')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->button()
                    ->size('sm')
                    ->color(fn (Sppd $record) => $record->status === 'pending' ? 'warning' : 'gray')
                    ->url(fn (Sppd $record) => static::getUrl('review', ['record' => $record]))
                    ->visible(fn (Sppd $record) => $record->status !== 'draft' || auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'HR'])),
                Tables\Actions\EditAction::make()
                    ->label(fn (Sppd $record) => $record->status === 'draft' ? 'Isi Form SPPD' : 'Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->visible(fn (Sppd $record) => static::canEdit($record)),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (Sppd $record) => route('sppds.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Sppd $record) => $record->status !== 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->icon('heroicon-o-trash')
                        ->visible(fn () => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppds::route('/'),
            'create' => Pages\CreateSppd::route('/create'),
            'review' => Pages\ReviewSppd::route('/{record}/review'),
            'edit' => Pages\EditSppd::route('/{record}/edit'),
        ];
    }
}
