<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages;
use App\Filament\Admin\Support\RecapTableSupport;
use App\Models\BusinessTrip;
use App\Models\OfficialTravelRecap;
use App\Models\OfficialTravelRecapItem;
use App\Models\ReimbursementSetting;
use App\Models\SppdCost;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class OfficialTravelRecapResource extends Resource
{
    protected static ?string $model = OfficialTravelRecap::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Rekap Perjalanan Dinas';

    protected static ?string $slug = 'official-travel-recaps';

    protected static ?string $modelLabel = 'Rekap Perjalanan Dinas';

    protected static ?string $pluralModelLabel = 'Rekap Biaya Perjalanan Dinas';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Division Head', 'Supervisor']);
    }

    public static function getHrWorkAreaId(): ?int
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasRole('HR') || $user->hasAnyRole(['Superadmin', 'Admin', 'Manager', 'Division Head'])) {
            return null;
        }

        return $user->employee?->work_area_id ? (int) $user->employee->work_area_id : null;
    }

    public static function canView(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        $hrAreaId = static::getHrWorkAreaId();
        if ($hrAreaId) {
            return static::getEloquentQuery()->whereKey($record->getKey())->exists();
        }

        return true;
    }

    public static function canCreate(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return false;
        }

        if ($user->hasAnyRole(['Superadmin', 'Admin'])) {
            return true;
        }

        return static::canView($record) && RecapTableSupport::latestApproval($record)?->status !== 'approved';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $hrAreaId = static::getHrWorkAreaId();
        if ($hrAreaId) {
            $userId = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->id;
            $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($hrAreaId, $userId) {
                if ($userId) {
                    $q->where('creator_id', $userId);
                }
                $q->orWhereHas('items.sppd.employee', fn (\Illuminate\Database\Eloquent\Builder $eq) => $eq->where('work_area_id', $hrAreaId))
                    ->orWhereHas('businessTrip.sppds.employee', fn (\Illuminate\Database\Eloquent\Builder $eq) => $eq->where('work_area_id', $hrAreaId));
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $setting = ReimbursementSetting::current();

        return $form
            ->schema([
                Forms\Components\Section::make('Sumber Data SPPD & Informasi Rekapitulasi')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description('Pilih dokumen/kegiatan SPPD untuk mengisi unit kerja, periode, penandatangan, serta seluruh uraian biaya SPPD pegawai secara otomatis.')
                    ->schema([
                        Forms\Components\Select::make('business_trip_ids')
                            ->label('Pilih Kegiatan / Dokumen SPPD dalam Periode Ini (Bisa pilih > 1)')
                            ->multiple()
                            ->options(function () {
                                $hrAreaId = static::getHrWorkAreaId();

                                return BusinessTrip::with(['memo.department', 'sppds.cost'])
                                    ->whereHas('sppds', function ($q) use ($hrAreaId) {
                                        $q->whereHas('cost');
                                        if ($hrAreaId) {
                                            $q->whereHas('employee', fn ($eq) => $eq->where('work_area_id', $hrAreaId));
                                        }
                                    })
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (BusinessTrip $t) => [
                                        $t->id => ($t->memo?->nomor_memo ? "{$t->memo->nomor_memo} — " : '') . $t->activity_name . ' (' . $t->sppds->count() . ' Pegawai)',
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($setting): void {
                                $ids = is_array($state) ? array_values(array_filter(array_map('intval', $state))) : ($state ? [(int) $state] : []);
                                if (empty($ids)) {
                                    return;
                                }
                                $hrAreaId = static::getHrWorkAreaId();
                                $trips = BusinessTrip::with(['memo.department', 'sppds.employee.department', 'sppds.cost'])->whereIn('id', $ids)->get();
                                if ($trips->isEmpty()) {
                                    return;
                                }

                                $firstTrip = $trips->first();
                                $allSppds = $trips->flatMap->sppds;
                                if ($hrAreaId) {
                                    $allSppds = $allSppds->filter(fn ($s) => (int) ($s->employee?->work_area_id ?? 0) === $hrAreaId)->values();
                                }
                                $unitNames = $allSppds->map(fn ($s) => $s->employee?->department?->name)->filter()->unique()->values();
                                if ($unitNames->isEmpty() && $firstTrip->memo?->department?->name) {
                                    $unitNames = collect([$firstTrip->memo->department->name]);
                                }
                                $deptId = $allSppds->first()?->employee?->department_id ?? $firstTrip->memo?->department_id;
                                $rawDate = $allSppds->pluck('departure_date')->filter()->sort()->first() ?? $firstTrip->start_date ?? now();
                                $periodDate = Carbon::parse($rawDate)->locale('id');

                                $set('department_id', $deptId);
                                $set('work_name', 'Pekerjaan ' . ($unitNames->isNotEmpty() ? $unitNames->implode(', ') : ($firstTrip->activity_name ?: 'Perjalanan Dinas')));
                                $set('period_month', $periodDate->translatedFormat('F'));
                                $set('period_label', $periodDate->translatedFormat('F Y'));
                                $set('manfee_percent', (float) $setting->travel_manfee_percent);

                                $sig = BusinessTrip::resolveSignatoriesForTrips($ids, $deptId);
                                if ($sig['prepared_by']) {
                                    $set('prepared_by_name', $sig['prepared_by']->name);
                                    $set('prepared_by_position', $sig['prepared_by']->position?->name ?? 'Business Division Head');
                                }
                                if ($sig['checked_by']->isNotEmpty()) {
                                    $set('checked_by_list', $sig['checked_by']->map(fn ($m) => [
                                        'name' => $m->name,
                                        'position' => $m->position?->name ?? 'Manajer',
                                    ])->toArray());
                                }
                                if ($sig['approved_by']) {
                                    $set('approved_by_name', $sig['approved_by']->name);
                                    $set('approved_by_position', $sig['approved_by']->position?->name ?? 'Division Head');
                                }

                                $autoItems = [];
                                foreach ($trips as $trip) {
                                    foreach ($trip->sppds as $sppd) {
                                        if (! $sppd->cost) {
                                            continue;
                                        }
                                        if ($hrAreaId && (int) ($sppd->employee?->work_area_id ?? 0) !== $hrAreaId) {
                                            continue;
                                        }
                                        $empName = $sppd->employee?->name ?? '-';
                                        $origin = $sppd->origin_location ?: 'Jakarta';
                                        $dest = $sppd->destination_location ?: ($trip->destination ?? '-');
                                        $dep = $sppd->departure_date ? Carbon::parse($sppd->departure_date)->locale('id') : null;
                                        $ret = $sppd->return_date ? Carbon::parse($sppd->return_date)->locale('id') : null;
                                        $dateText = ($dep && $ret && ! $dep->isSameDay($ret))
                                            ? ($dep->isSameMonth($ret) && $dep->isSameYear($ret)
                                                ? $dep->format('d') . '-' . $ret->translatedFormat('d F Y')
                                                : $dep->translatedFormat('d F Y') . ' - ' . $ret->translatedFormat('d F Y'))
                                            : ($dep ? $dep->translatedFormat('d F Y') : '-');

                                        $autoItems[] = [
                                            'sppd_id' => $sppd->id,
                                            'sppd_cost_id' => $sppd->cost->id,
                                            'description' => "SPPD {$empName} dari {$origin} ke {$dest} tanggal {$dateText}",
                                            'amount' => (float) $sppd->cost->total_cost,
                                        ];
                                    }
                                }
                                if (! empty($autoItems)) {
                                    $set('items', $autoItems);
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('work_name'),
                        Forms\Components\Hidden::make('period_month'),
                        Forms\Components\Hidden::make('period_label'),
                        Forms\Components\Select::make('department_id')
                            ->label('Unit Kerja')
                            ->relationship('department', 'name')
                            ->default(fn () => \App\Models\Department::where('code', 'MESI')->value('id') ?? \App\Models\Department::first()?->id)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('manfee_percent')
                            ->label('Persentase Management Fee (%)')
                            ->numeric()
                            ->step('0.01')
                            ->suffix('%')
                            ->default(fn () => (float) ReimbursementSetting::current()->travel_manfee_percent)
                            ->minValue(0)
                            ->maxValue(fn () => (float) ReimbursementSetting::current()->travel_manfee_percent)
                            ->helperText(fn () => 'Maksimal ' . rtrim(rtrim(number_format((float) ReimbursementSetting::current()->travel_manfee_percent, 2, ',', '.'), '0'), ',') . '% sesuai pengaturan Management Fee.')
                            ->validationMessages([
                                'max' => 'Persentase Management Fee tidak boleh melebihi batas maksimal pengaturan (:max%).',
                                'min' => 'Persentase Management Fee tidak boleh kurang dari 0%.',
                            ])
                            ->required(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Rincian Biaya Perjalanan Dinas (SPPD)')
                    ->icon('heroicon-o-list-bullet')
                    ->iconColor('info')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship(
                                'items',
                                modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                                    $hrAreaId = static::getHrWorkAreaId();
                                    if ($hrAreaId) {
                                        $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($hrAreaId) {
                                            $q->whereNull('sppd_id')
                                                ->orWhereHas('sppd.employee', fn (\Illuminate\Database\Eloquent\Builder $eq) => $eq->where('work_area_id', $hrAreaId));
                                        });
                                    }

                                    return $query;
                                }
                            )
                            ->label('Daftar SPPD')
                            ->addActionLabel('Tambah Baris SPPD')
                            ->reorderableWithButtons()
                            ->orderColumn('sort_order')
                            ->schema([
                                Forms\Components\Select::make('sppd_cost_id')
                                    ->label('Ambil dari Biaya SPPD (Opsional)')
                                    ->options(function () {
                                        $hrAreaId = static::getHrWorkAreaId();

                                        return SppdCost::with('sppd.employee')
                                            ->when($hrAreaId, fn ($q) => $q->whereHas('sppd.employee', fn ($eq) => $eq->where('work_area_id', $hrAreaId)))
                                            ->orderByDesc('id')
                                            ->get()
                                            ->mapWithKeys(function (SppdCost $c): array {
                                                $sppd = $c->sppd;
                                                $label = ($sppd?->sppd_number ?: 'Draf') . ' - ' . ($sppd?->employee?->name ?? '-') . ' (Rp ' . number_format((float) $c->total_cost, 0, ',', '.') . ')';

                                                return [$c->id => $label];
                                            })->toArray();
                                    })
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                        if (! $state) {
                                            return;
                                        }
                                        $cost = SppdCost::with('sppd.employee')->find($state);
                                        $sppd = $cost?->sppd;
                                        if (! $cost || ! $sppd) {
                                            return;
                                        }

                                        $set('sppd_id', $sppd->id);
                                        $set('amount', (float) $cost->total_cost);

                                        $name = $sppd->employee?->name ?? '-';
                                        $origin = $sppd->origin_location ?: 'Jakarta';
                                        $dest = $sppd->destination_location ?: '-';
                                        $dep = $sppd->departure_date ? Carbon::parse($sppd->departure_date)->locale('id') : null;
                                        $ret = $sppd->return_date ? Carbon::parse($sppd->return_date)->locale('id') : null;
                                        $dateText = ($dep && $ret && ! $dep->isSameDay($ret))
                                            ? ($dep->isSameMonth($ret) && $dep->isSameYear($ret)
                                                ? $dep->format('d') . '-' . $ret->translatedFormat('d F Y')
                                                : $dep->translatedFormat('d F Y') . ' - ' . $ret->translatedFormat('d F Y'))
                                            : ($dep ? $dep->translatedFormat('d F Y') : '-');

                                        $set('description', "SPPD {$name} dari {$origin} ke {$dest} tanggal {$dateText}");
                                    })
                                    ->columnSpanFull(),
                                Forms\Components\Hidden::make('sppd_id'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Uraian Pekerjaan')
                                    ->placeholder('Contoh: SPPD Chairunnisa dari Jakarta ke Lampung tanggal 06-10 April 2026')
                                    ->rows(2)
                                    ->required()
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Jumlah Biaya')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(['default' => 1, 'md' => 3])
                            ->defaultItems(1)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('period_label')
                        ->label('Periode')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('work_name')
                        ->label('Pekerjaan')
                        ->searchable()
                        ->limit(55),
                    Tables\Columns\TextColumn::make('grand_total')
                        ->label('Total Biaya Pekerjaan + Fee')
                        ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                        ->badge()
                        ->color('success')
                        ->grow(false),
                    RecapTableSupport::statusColumn()->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('items_count')
                                ->description('Jumlah SPPD', position: 'above')
                                ->counts('items')
                                ->badge()
                                ->color('info'),
                            Tables\Columns\TextColumn::make('total_work_cost')
                                ->description('Total Biaya Pekerjaan', position: 'above')
                                ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),
                            Tables\Columns\TextColumn::make('manfee_amount')
                                ->description('Management Fee', position: 'above')
                                ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Dibuat Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                RecapTableSupport::monthYearFilter('created_at', 'period_label'),
            ])
            ->actions(RecapTableSupport::actions('recaps.travel.print'))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ringkasan Rekap Perjalanan Dinas')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Infolists\Components\TextEntry::make('work_name')->label('Pekerjaan'),
                        Infolists\Components\TextEntry::make('period_label')->label('Periode'),
                        Infolists\Components\TextEntry::make('approval_status')
                            ->label('Status Persetujuan')
                            ->badge()
                            ->state(function (OfficialTravelRecap $record): string {
                                $req = RecapTableSupport::latestApproval($record);
                                if (! $req) {
                                    return 'Belum Diajukan';
                                }
                                if ($req->status === 'approved') {
                                    return 'Disetujui';
                                }
                                if ($req->status === 'rejected') {
                                    return 'Ditolak';
                                }

                                $resolverClass = $req->currentStep()?->policyStep?->resolver_class;
                                if ($resolverClass === \App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver::class) {
                                    return 'Menunggu Division Head Perkasa';
                                }

                                return $resolverClass === \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class
                                    ? 'Menunggu Division Head'
                                    : 'Menunggu Pemeriksaan Manajer';
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Disetujui' => 'success',
                                'Ditolak' => 'danger',
                                'Menunggu Division Head Perkasa', 'Menunggu Pemeriksaan Manajer', 'Menunggu Division Head' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('total_work_cost')->label('Total Biaya Pekerjaan')->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),
                        Infolists\Components\TextEntry::make('manfee_amount')
                            ->label('Management Fee (' . rtrim(rtrim(number_format((float) ($infolist->getRecord()?->manfee_percent ?? 6), 2, '.', ''), '0'), '.') . '%)')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),
                        Infolists\Components\TextEntry::make('grand_total')
                            ->label('Total Biaya Akhir')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                            ->weight('bold')
                            ->color('success'),
                    ]),

                Infolists\Components\Section::make('Rincian Biaya Perjalanan Dinas (SPPD)')
                    ->icon('heroicon-o-list-bullet')
                    ->iconColor('info')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->getStateUsing(function (OfficialTravelRecap $record) {
                                $hrAreaId = static::getHrWorkAreaId();
                                $items = $record->items()->with(['sppd.employee', 'sppdCost'])->get();
                                if (! $hrAreaId) {
                                    return $items;
                                }

                                return $items->filter(fn (OfficialTravelRecapItem $item) => ! $item->sppd || ! $item->sppd->employee?->work_area_id || (int) $item->sppd->employee->work_area_id === $hrAreaId)->values();
                            })
                            ->label('')
                            ->columns(['default' => 1, 'md' => 4])
                            ->schema([
                                Infolists\Components\TextEntry::make('sppd.sppd_number')
                                    ->label('Nomor SPPD')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Uraian Pekerjaan')
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Total Biaya')
                                    ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                                    ->weight('bold')
                                    ->color('success'),
                                Infolists\Components\ViewEntry::make('cost_breakdown')
                                    ->view('filament.infolists.official-travel-cost-breakdown')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                RecapTableSupport::signatureVerificationInfolistSection(),
            ]);
    }

    protected static function formatCostBreakdownHtml(OfficialTravelRecapItem $item): HtmlString
    {
        $cost = $item->sppdCost ?? $item->sppd?->cost;

        if (! $cost) {
            return new HtmlString('<span class="text-xs text-gray-500">Rincian biaya SPPD belum tersedia.</span>');
        }

        $idr = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $groups = [
            [
                'title' => 'Transportasi',
                'subtotal' => (float) $cost->subtotal_transport,
                'items' => [
                    'Transportasi Terminal / Bandara' => (float) $cost->transport_terminal_total,
                    'Tiket Transportasi' => (float) $cost->transport_ticket_total,
                    'Transportasi Lokal' => (float) $cost->transport_local_total,
                ],
            ],
            [
                'title' => 'Uang Harian & Penginapan',
                'subtotal' => (float) $cost->subtotal_per_diem,
                'items' => [
                    'Hotel / Penginapan' => (float) $cost->hotel_total,
                    'Uang Makan' => (float) $cost->meal_total,
                    'Reimburse Uang Makan' => (float) $cost->meal_reimbursement_total,
                    'Transit Lokal' => (float) $cost->local_transit_total,
                    'Uang Saku' => (float) $cost->pocket_money_total,
                    'Harian Luar Negeri' => (float) $cost->overseas_daily_allowance,
                    'Hotel & Makan Luar Negeri' => (float) $cost->overseas_hotel_meal,
                    'Transport Luar Negeri' => (float) $cost->overseas_transport,
                    'Uang Saku Luar Negeri' => (float) $cost->overseas_pocket_money,
                    'Tunjangan Bulanan Luar Negeri' => (float) $cost->overseas_monthly_allowance,
                ],
            ],
            [
                'title' => 'Biaya Lain-lain',
                'subtotal' => (float) $cost->subtotal_other,
                'items' => [
                    'Airport Tax' => (float) $cost->other_airport_tax,
                    'Laundry' => (float) $cost->other_laundry,
                    'Perlengkapan' => (float) $cost->other_equipment,
                    'Buku / Makalah' => (float) $cost->other_book_paper,
                    'Asesor' => (float) $cost->other_assessor,
                    'Sewa Kendaraan' => (float) $cost->other_car_rental,
                    'Bagasi' => (float) $cost->other_baggage,
                    'Tol / BBM / Parkir' => (float) $cost->other_toll_fuel_parking,
                ],
            ],
        ];

        $cards = '';
        foreach ($groups as $group) {
            $rows = '';
            foreach ($group['items'] as $label => $val) {
                if ($val <= 0) {
                    continue;
                }
                $rows .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;font-size:12px;line-height:1.6;padding:3px 0;">'
                    . '<span style="color:#6b7280;">' . e($label) . '</span>'
                    . '<span style="font-weight:500;font-variant-numeric:tabular-nums;white-space:nowrap;">' . e($idr($val)) . '</span>'
                    . '</div>';
            }

            if ($rows === '' && $group['subtotal'] <= 0) {
                continue;
            }

            $cards .= '<div style="border:1px solid rgba(156,163,175,0.28);border-radius:10px;overflow:hidden;background:rgba(156,163,175,0.03);">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:12px;font-weight:700;padding:8px 12px;background:rgba(156,163,175,0.08);border-bottom:1px solid rgba(156,163,175,0.2);">'
                . '<span>' . e($group['title']) . '</span>'
                . '<span style="font-variant-numeric:tabular-nums;white-space:nowrap;">' . e($idr($group['subtotal'])) . '</span>'
                . '</div>'
                . '<div style="padding:8px 12px;">' . $rows . '</div>'
                . '</div>';
        }

        $printUrl = route('sppd-costs.print', $cost);
        $footer = '<div style="margin-top:12px;padding-top:10px;border-top:1px dashed rgba(156,163,175,0.35);display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;font-size:12px;">'
            . '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;color:#6b7280;">'
            . '<span>Lama Dinas: <strong style="color:inherit;">' . (int) $cost->official_days . ' hari</strong></span>'
            . '<span>&bull;</span>'
            . '<span>Total Rincian SPPD: <strong style="color:#059669;font-variant-numeric:tabular-nums;">' . e($idr($cost->total_cost)) . '</strong></span>'
            . '</div>'
            . '<a href="' . e($printUrl) . '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:600;text-decoration:none;white-space:nowrap;">'
            . 'Lihat Rincian SPPD (PDF) &rarr;'
            . '</a>'
            . '</div>';

        return new HtmlString(
            '<div style="width:100%;display:block;">'
            . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:12px;width:100%;">' . $cards . '</div>'
            . $footer
            . '</div>'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfficialTravelRecaps::route('/'),
            'create' => Pages\CreateOfficialTravelRecap::route('/create'),
            'view' => Pages\ViewOfficialTravelRecap::route('/{record}'),
            'edit' => Pages\EditOfficialTravelRecap::route('/{record}/edit'),
        ];
    }
}
