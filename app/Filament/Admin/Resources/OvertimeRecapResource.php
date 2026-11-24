<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OvertimeRecapResource\Pages;
use App\Models\Department;
use App\Models\OvertimeRecap;
use App\Models\OvertimeRecapItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class OvertimeRecapResource extends Resource
{
    protected static ?string $model = OvertimeRecap::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Rekap Bulanan Lembur';

    protected static ?string $slug = 'overtime-recaps';

    protected static ?string $modelLabel = 'Rekap Lembur';

    protected static ?string $pluralModelLabel = 'Rekapitulasi Biaya Lembur';

    protected static ?int $navigationSort = 3;

    protected static function currentUser(): ?\App\Models\User
    {
        return (\Filament\Facades\Filament::getCurrentPanel() ? \Filament\Facades\Filament::auth()->user() : null) ?? auth()->user();
    }

    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Division Head', 'Supervisor']);
    }

    public static function canCreate(): bool
    {
        $user = static::currentUser();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = static::currentUser();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function form(Form $form): Form
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $form
            ->schema([
                Forms\Components\Section::make('Periode & Lingkup Rekapitulasi Lembur')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul Rekapitulasi')
                            ->default('Rekapitulasi Biaya Reimbursement Lembur Personil')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('period_month')
                            ->label('Bulan')
                            ->options($months)
                            ->default((int) now()->month)
                            ->required(),
                        Forms\Components\TextInput::make('period_year')
                            ->label('Tahun')
                            ->numeric()
                            ->default((int) now()->year)
                            ->required(),
                        Forms\Components\Select::make('department_id')
                            ->label('Departemen / Unit (Opsional)')
                            ->options(Department::orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Departemen')
                            ->searchable(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ringkasan Rekapitulasi Biaya Lembur')
                    ->schema([
                        Infolists\Components\TextEntry::make('recap_number')
                            ->label('Nomor Rekap')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('title')
                            ->label('Judul Rekap')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('period_label')
                            ->label('Periode')
                            ->state(fn (OvertimeRecap $record): string => sprintf('%02d / %d', $record->period_month, $record->period_year)),
                        Infolists\Components\TextEntry::make('total_shift_amount')
                            ->label('Total Lembur Shift')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        Infolists\Components\TextEntry::make('total_non_shift_amount')
                            ->label('Total Lembur Non-Shift')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        Infolists\Components\TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->weight('bold')
                            ->color('success')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),

                Infolists\Components\Section::make('Rincian Per Personil (Shift vs Non-Shift)')
                    ->schema([
                        Infolists\Components\TextEntry::make('items_table')
                            ->hiddenLabel()
                            ->state(function (OvertimeRecap $record): HtmlString {
                                $record->loadMissing(['items.employee.position']);
                                if ($record->items->isEmpty()) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Belum ada SPKL berstatus disetujui penuh pada periode ini.</p>');
                                }

                                $rows = '';
                                $no = 1;
                                foreach ($record->items as $item) {
                                    /** @var OvertimeRecapItem $item */
                                    $nip = e($item->employee?->identity_number ?? '-');
                                    $name = e($item->employee?->name ?? '-');
                                    $pos = e($item->employee?->position?->name ?? '-');
                                    $shift = number_format($item->shift_amount, 0, ',', '.');
                                    $nonShift = number_format($item->non_shift_amount, 0, ',', '.');
                                    $total = number_format($item->total_amount, 0, ',', '.');

                                    $rows .= "<tr class='border-b border-gray-200 dark:border-gray-700'>
                                        <td class='px-3 py-2 text-center'>{$no}</td>
                                        <td class='px-3 py-2'>{$nip}</td>
                                        <td class='px-3 py-2 font-medium'>{$name}</td>
                                        <td class='px-3 py-2'>{$pos}</td>
                                        <td class='px-3 py-2 text-right'>{$item->shift_hours} jam<br><span class='font-semibold'>Rp {$shift}</span></td>
                                        <td class='px-3 py-2 text-right'>{$item->non_shift_hours} jam<br><span class='font-semibold'>Rp {$nonShift}</span></td>
                                        <td class='px-3 py-2 text-right font-bold'>Rp {$total}</td>
                                    </tr>";
                                    $no++;
                                }

                                $html = "<div class='overflow-x-auto'>
                                    <table class='w-full text-sm text-left'>
                                        <thead class='bg-gray-100 dark:bg-gray-800 text-xs uppercase'>
                                            <tr>
                                                <th class='px-3 py-2 text-center'>No</th>
                                                <th class='px-3 py-2'>NIP</th>
                                                <th class='px-3 py-2'>Nama</th>
                                                <th class='px-3 py-2'>Posisi</th>
                                                <th class='px-3 py-2 text-right'>Lembur Shift</th>
                                                <th class='px-3 py-2 text-right'>Lembur Non-Shift</th>
                                                <th class='px-3 py-2 text-right'>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                </div>";

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('period_month')
                        ->label('Periode')
                        ->formatStateUsing(fn (OvertimeRecap $record): string => sprintf('%02d / %d', $record->period_month, $record->period_year))
                        ->weight('bold')
                        ->grow(false),
                    Tables\Columns\TextColumn::make('title')
                        ->label('Judul')
                        ->searchable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('grand_total')
                        ->label('Grand Total')
                        ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                        ->badge()
                        ->color('success')
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('recap_number')
                                ->description('Nomor Rekap', position: 'above')
                                ->badge()
                                ->color('info')
                                ->searchable(),
                            Tables\Columns\TextColumn::make('items_count')
                                ->description('Jumlah Personil', position: 'above')
                                ->counts('items')
                                ->badge()
                                ->color('primary'),
                            Tables\Columns\TextColumn::make('total_shift_amount')
                                ->description('Total Lembur Shift', position: 'above')
                                ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                            Tables\Columns\TextColumn::make('total_non_shift_amount')
                                ->description('Total Lembur Non-Shift', position: 'above')
                                ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                \App\Filament\Admin\Support\RecapTableSupport::monthYearFilter('created_at'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->size('sm')
                    ->color('primary'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (OvertimeRecap $record): string => route('overtime-recaps.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->size('sm'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvertimeRecaps::route('/'),
            'create' => Pages\CreateOvertimeRecap::route('/create'),
            'view' => Pages\ViewOvertimeRecap::route('/{record}'),
        ];
    }
}
