<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages;
use App\Filament\Admin\Support\RecapTableSupport;
use App\Models\BusinessTrip;
use App\Models\ReimbursementSetting;
use App\Models\Sppd;
use App\Models\TransportAccommodationRecap;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TransportAccommodationRecapResource extends Resource
{
    protected static ?string $model = TransportAccommodationRecap::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Rekap Tiket & Akomodasi';

    protected static ?string $slug = 'transport-accommodation-recaps';

    protected static ?string $modelLabel = 'Rekap Tiket & Akomodasi';

    protected static ?string $pluralModelLabel = 'Rekap Biaya Tiket & Akomodasi';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Division Head', 'Supervisor']);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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

        return RecapTableSupport::latestApproval($record)?->status !== 'approved';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Form $form): Form
    {
        $setting = ReimbursementSetting::current();

        return $form
            ->schema([
                Forms\Components\Section::make('Sumber Data SPPD & Informasi Rekap')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description('Pilih dokumen/kegiatan SPPD dan mode pengelompokan (Per Pegawai atau Gabungan 1 Invoice) untuk mengisi rincian otomatis.')
                    ->schema([
                        Forms\Components\Select::make('business_trip_ids')
                            ->label('Pilih Kegiatan / Dokumen SPPD dalam Periode Ini (Bisa pilih > 1)')
                            ->multiple()
                            ->options(fn () => BusinessTrip::with(['memo.department', 'sppds.cost'])
                                ->whereHas('sppds.cost')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn (BusinessTrip $t) => [
                                    $t->id => ($t->memo?->nomor_memo ? "{$t->memo->nomor_memo} — " : '') . $t->activity_name . ' (' . $t->sppds->count() . ' Pegawai)',
                                ])
                                ->toArray())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::populateFromBusinessTrip($state, $get('grouping_mode') ?? 'per_employee', $set, $setting))
                            ->columnSpanFull(),
                        Forms\Components\Radio::make('grouping_mode')
                            ->label('Mode Pengelompokan Baris Invoice')
                            ->options([
                                'per_employee' => 'Per Pegawai (1 baris per pegawai)',
                                'combined' => 'Gabungan / Akumulasi 1 Invoice (1 baris untuk semua pegawai dalam SPPD)',
                            ])
                            ->default('per_employee')
                            ->inline()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::populateFromBusinessTrip($get('business_trip_ids'), $state ?? 'per_employee', $set, $setting))
                            ->helperText('Pilih "Gabungan / Akumulasi 1 Invoice" jika kwitansi hotel atau tiket beberapa pegawai digabung dalam 1 dokumen invoice.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('department_id')
                            ->label('Unit Kerja')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('period_month')
                            ->label('Bulan & Tahun Periode')
                            ->placeholder('Contoh: November 2026')
                            ->default(fn () => Carbon::now()->locale('id')->translatedFormat('F Y'))
                            ->required()
                            ->maxLength(50),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Rincian Biaya Transportasi & Akomodasi')
                    ->icon('heroicon-o-list-bullet')
                    ->iconColor('info')
                    ->description('Anda dapat memilih satu atau beberapa pegawai sekaligus pada tiap baris bila tagihan digabung dalam 1 invoice.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->label('Daftar Item Transportasi / Hotel')
                            ->addActionLabel('Tambah Item')
                            ->reorderableWithButtons()
                            ->orderColumn('sort_order')
                            ->schema([
                                Forms\Components\Select::make('sppd_ids')
                                    ->label('Pegawai / SPPD Terkait (Bisa pilih > 1 untuk invoice gabungan)')
                                    ->multiple()
                                    ->options(fn () => Sppd::with('employee')->orderByDesc('id')->get()->mapWithKeys(fn (Sppd $s) => [
                                        $s->id => ($s->sppd_number ?: 'Draf') . ' - ' . ($s->employee?->name ?? '-'),
                                    ])->toArray())
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::populateItemFromSppds($state, $get('item_category') ?? 'transport', $set, $get, $setting))
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                Forms\Components\Select::make('item_category')
                                    ->label('Kategori Item')
                                    ->options([
                                        'transport' => 'Tiket / Transportasi',
                                        'hotel' => 'Hotel / Akomodasi',
                                    ])
                                    ->default('transport')
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::populateItemFromSppds($get('sppd_ids'), $state ?? 'transport', $set, $get, $setting))
                                    ->columnSpan(1),
                                Forms\Components\Textarea::make('description')
                                    ->label('Keterangan')
                                    ->placeholder('Contoh: Tiket Pesawat Dewi Fitriana - Mita Septiana dari CGK-BTH tanggal 17 Februari 2026')
                                    ->rows(2)
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Jumlah (IDR)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Masukkan nominal sesuai invoice')
                                    ->required()
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($setting): void {
                                        $amount = (float) ($state ?? 0);
                                        $hasInvoice = ! empty($get('evidence_files'));
                                        $serviceFeePercent = (float) $setting->transport_service_fee_percent;
                                        $serviceFee = ($hasInvoice && $serviceFeePercent > 0)
                                            ? round($amount * ($serviceFeePercent / 100), 0)
                                            : (float) ($get('service_fee') ?? 0);
                                        if ($hasInvoice && $serviceFeePercent > 0) {
                                            $set('service_fee', $serviceFee);
                                        }
                                        $set('total_amount', $amount + $serviceFee);
                                    }),
                                Forms\Components\TextInput::make('service_fee')
                                    ->label('Service Fee (IDR)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required()
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $amount = (float) ($get('amount') ?? 0);
                                        $serviceFee = (float) ($state ?? 0);
                                        $set('total_amount', $amount + $serviceFee);
                                    }),
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Total Biaya (IDR)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0),
                                Forms\Components\FileUpload::make('evidence_files')
                                    ->label('Invoice / Bukti Tiket atau Akomodasi (Foto atau PDF)')
                                    ->multiple()
                                    ->disk('public')
                                    ->directory('recap-evidences/transport')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                    ->openable()
                                    ->downloadable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) use ($setting): void {
                                        $amount = (float) ($get('amount') ?? 0);
                                        $serviceFeePercent = (float) $setting->transport_service_fee_percent;
                                        if (! empty($state) && $serviceFeePercent > 0) {
                                            $fee = round($amount * ($serviceFeePercent / 100), 0);
                                            $set('service_fee', $fee);
                                            $set('total_amount', $amount + $fee);
                                        }
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 3])
                            ->defaultItems(1)
                            ->required(),
                    ]),
            ]);
    }

    public static function populateFromBusinessTrip(mixed $tripIds, string $mode, Forms\Set $set, ReimbursementSetting $setting): void
    {
        $ids = is_array($tripIds) ? array_values(array_filter(array_map('intval', $tripIds))) : ($tripIds ? [(int) $tripIds] : []);
        if (empty($ids)) {
            return;
        }

        $trips = BusinessTrip::with(['memo.department', 'sppds.employee.department', 'sppds.cost'])->whereIn('id', $ids)->get();
        if ($trips->isEmpty()) {
            return;
        }

        $firstTrip = $trips->first();
        $allSppds = $trips->flatMap->sppds;
        $deptId = $allSppds->first()?->employee?->department_id ?? $firstTrip->memo?->department_id;
        $rawDate = $allSppds->pluck('departure_date')->filter()->sort()->first() ?? $firstTrip->start_date ?? now();
        $periodDate = Carbon::parse($rawDate)->locale('id');

        $set('department_id', $deptId);
        $set('period_month', $periodDate->translatedFormat('F Y'));

        $autoItems = [];
        if ($mode === 'combined') {
            $transportSppdIds = [];
            $transportNames = [];
            $transportTotal = 0.0;

            $hotelSppdIds = [];
            $hotelNames = [];
            $hotelTotal = 0.0;

            foreach ($allSppds as $sppd) {
                $cost = $sppd->cost;
                if (! $cost) {
                    continue;
                }
                $empName = $sppd->employee?->name ?? '-';
                $tAmt = (float) $cost->transport_terminal_total + (float) $cost->transport_ticket_total + (float) $cost->transport_local_total + (float) $cost->overseas_transport;
                $hAmt = (float) $cost->hotel_total + (float) $cost->overseas_hotel_meal;

                if ($tAmt > 0) {
                    $transportSppdIds[] = $sppd->id;
                    $transportNames[] = $empName;
                    $transportTotal += $tAmt;
                }
                if ($hAmt > 0) {
                    $hotelSppdIds[] = $sppd->id;
                    $hotelNames[] = $empName;
                    $hotelTotal += $hAmt;
                }
            }

            $dest = $trips->pluck('destination')->filter()->unique()->implode(', ') ?: '-';
            $dateText = $periodDate->translatedFormat('F Y');

            if ($transportTotal > 0 || ! empty($transportNames)) {
                $autoItems[] = [
                    'sppd_ids' => $transportSppdIds,
                    'item_category' => 'transport',
                    'description' => 'Tiket / Transportasi ' . implode(' - ', array_unique($transportNames)) . " tujuan {$dest} periode {$dateText}",
                    'amount' => null,
                    'service_fee' => 0,
                    'total_amount' => 0,
                    'evidence_files' => [],
                ];
            }

            if ($hotelTotal > 0 || ! empty($hotelNames)) {
                $autoItems[] = [
                    'sppd_ids' => $hotelSppdIds,
                    'item_category' => 'hotel',
                    'description' => 'Hotel / Akomodasi an ' . implode(', ', array_unique($hotelNames)) . " di {$dest} periode {$dateText}",
                    'amount' => null,
                    'service_fee' => 0,
                    'total_amount' => 0,
                    'evidence_files' => [],
                ];
            }
        } else {
            foreach ($trips as $trip) {
                foreach ($trip->sppds as $sppd) {
                    $cost = $sppd->cost;
                    if (! $cost) {
                        continue;
                    }
                    $empName = $sppd->employee?->name ?? '-';
                    $origin = $sppd->origin_location ?: 'Jakarta';
                    $dest = $sppd->destination_location ?: ($trip->destination ?? '-');
                    $tAmt = (float) $cost->transport_terminal_total + (float) $cost->transport_ticket_total + (float) $cost->transport_local_total + (float) $cost->overseas_transport;
                    $hAmt = (float) $cost->hotel_total + (float) $cost->overseas_hotel_meal;

                    if ($tAmt > 0) {
                        $autoItems[] = [
                            'sppd_ids' => [$sppd->id],
                            'item_category' => 'transport',
                            'description' => "Transportasi {$empName} ({$origin} - {$dest})",
                            'amount' => null,
                            'service_fee' => 0,
                            'total_amount' => 0,
                            'evidence_files' => [],
                        ];
                    }
                    if ($hAmt > 0) {
                        $autoItems[] = [
                            'sppd_ids' => [$sppd->id],
                            'item_category' => 'hotel',
                            'description' => "Akomodasi / Hotel {$empName} di {$dest}",
                            'amount' => null,
                            'service_fee' => 0,
                            'total_amount' => 0,
                            'evidence_files' => [],
                        ];
                    }
                }
            }
        }

        if (! empty($autoItems)) {
            $set('items', $autoItems);
        }
    }

    public static function populateItemFromSppds(mixed $sppdIds, string $category, Forms\Set $set, Forms\Get $get, ReimbursementSetting $setting): void
    {
        $ids = is_array($sppdIds) ? array_filter($sppdIds) : [];
        if (empty($ids)) {
            return;
        }

        $sppds = Sppd::with(['employee', 'cost', 'businessTrip'])->whereIn('id', $ids)->get();
        if ($sppds->isEmpty()) {
            return;
        }

        $names = $sppds->map(fn ($s) => $s->employee?->name ?? '-')->values()->all();
        $first = $sppds->first();
        $origin = $first->origin_location ?: 'Jakarta';
        $dest = $first->destination_location ?: ($first->businessTrip?->destination ?? '-');

        $totalAmount = 0.0;
        foreach ($sppds as $sppd) {
            if (! $sppd->cost) {
                continue;
            }
            $totalAmount += $category === 'hotel'
                ? (float) $sppd->cost->hotel_total + (float) $sppd->cost->overseas_hotel_meal
                : (float) $sppd->cost->transport_terminal_total + (float) $sppd->cost->transport_ticket_total + (float) $sppd->cost->transport_local_total + (float) $sppd->cost->overseas_transport;
        }

        $desc = $category === 'hotel'
            ? 'Hotel / Akomodasi an ' . implode(', ', $names) . " di {$dest}"
            : 'Tiket / Transportasi ' . implode(' - ', $names) . " ({$origin} - {$dest})";

        $hasInvoice = ! empty($get('evidence_files'));
        $serviceFeePercent = (float) $setting->transport_service_fee_percent;
        $fee = ($hasInvoice && $serviceFeePercent > 0)
            ? round($totalAmount * ($serviceFeePercent / 100), 0)
            : (float) ($get('service_fee') ?? 0);

        $set('description', $desc);
        $amount = (float) ($get('amount') ?? 0);
        $set('total_amount', $amount + $fee);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                        ->label('Total Biaya + Manfee')
                        ->money('IDR', locale: 'id')
                        ->badge()
                        ->color('success')
                        ->grow(false),
                    RecapTableSupport::statusColumn()->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('items_count')
                                ->description('Jumlah Item', position: 'above')
                                ->counts('items')
                                ->badge()
                                ->color('info'),
                            Tables\Columns\TextColumn::make('total_cost')
                                ->description('Total Biaya', position: 'above')
                                ->money('IDR', locale: 'id'),
                            Tables\Columns\TextColumn::make('manfee_amount')
                                ->description('Manfee', position: 'above')
                                ->money('IDR', locale: 'id'),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Dibuat Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                RecapTableSupport::monthYearFilter('created_at', 'period_month'),
            ])
            ->actions(RecapTableSupport::actions('recaps.transport.print'))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ringkasan Rekap Tiket & Akomodasi')
                    ->icon('heroicon-o-document-text')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Infolists\Components\TextEntry::make('work_name')->label('Pekerjaan'),
                        Infolists\Components\TextEntry::make('period_month')->label('Periode'),
                        Infolists\Components\TextEntry::make('approval_status')
                            ->label('Status Persetujuan')
                            ->badge()
                            ->state(function (TransportAccommodationRecap $record): string {
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

                                return $req->currentStep()?->policyStep?->resolver_class === \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class
                                    ? 'Menunggu Division Head'
                                    : 'Menunggu Pemeriksaan Manajer';
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Disetujui' => 'success',
                                'Ditolak' => 'danger',
                                'Menunggu Pemeriksaan Manajer', 'Menunggu Division Head' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('total_cost')->label('Total Biaya')->money('IDR', locale: 'id'),
                        Infolists\Components\TextEntry::make('manfee_amount')
                            ->label('Manfee (' . rtrim(rtrim(number_format((float) ($infolist->getRecord()?->manfee_percent ?? 5), 2, '.', ''), '0'), '.') . '%)')
                            ->money('IDR', locale: 'id'),
                        Infolists\Components\TextEntry::make('grand_total')
                            ->label('Total Biaya & Manfee')
                            ->money('IDR', locale: 'id')
                            ->weight('bold')
                            ->color('success'),
                    ]),

                Infolists\Components\Section::make('Rincian Item & Invoice / Evidence')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->columns(['default' => 1, 'md' => 4])
                            ->schema([
                                Infolists\Components\TextEntry::make('description')->label('Keterangan')->columnSpan(['default' => 1, 'md' => 2]),
                                Infolists\Components\TextEntry::make('amount')->label('Jumlah')->money('IDR', locale: 'id'),
                                Infolists\Components\TextEntry::make('service_fee')->label('Service Fee')->money('IDR', locale: 'id'),
                                Infolists\Components\TextEntry::make('total_amount')->label('Total Biaya')->money('IDR', locale: 'id')->weight('bold'),
                                Infolists\Components\TextEntry::make('evidence_files')
                                    ->label('Bukti / Invoice')
                                    ->html()
                                    ->state(function ($record): string {
                                        $files = is_array($record->evidence_files) ? $record->evidence_files : [];
                                        if (empty($files)) {
                                            return '<span class="text-gray-400">Tidak ada lampiran</span>';
                                        }

                                        return collect($files)->map(function ($path, $i) {
                                            $url = e(route('attachments.show', ['path' => $path]));
                                            $name = e(basename($path));

                                            return "<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 underline mr-3\">Lampiran " . ($i + 1) . " ({$name})</a>";
                                        })->implode(' ');
                                    })
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportAccommodationRecaps::route('/'),
            'create' => Pages\CreateTransportAccommodationRecap::route('/create'),
            'view' => Pages\ViewTransportAccommodationRecap::route('/{record}'),
            'edit' => Pages\EditTransportAccommodationRecap::route('/{record}/edit'),
        ];
    }
}
