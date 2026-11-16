<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemoResource\Pages;
use App\Filament\Admin\Resources\MemoResource\RelationManagers;
use App\Models\Memo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MemoResource extends Resource
{
    protected static ?string $model = Memo::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Dokumen';
    protected static ?string $navigationLabel = 'Memo';
    protected static ?string $modelLabel = 'Memo';
    protected static ?string $pluralModelLabel = 'Memo';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // Hanya HR dan Superadmin yang memiliki akses menu Memo (Staff, Supervisor, Manager tidak diizinkan)
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // Staff tidak boleh mendapatkan akses Memo
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
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

        if ($user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return true;
        }

        // Manager tetap bisa membuka halaman ReviewMemo dari antrian Persetujuan Memo
        if ($user->hasRole('Manager') || $user->can('approve_memo')) {
            return true;
        }

        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return $record->status === 'draft';
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
        return 'Total Dokumen Memo';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        if (! $user || $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($employee) {
            $q->where('creator_employee_id', $employee->id)
                ->orWhere('department_id', $employee->department_id);
        });
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
                    // When reviewing memo from approvals or by manager, "Memo" should not be marked active
                    if (request()->routeIs('filament.admin.resources.memos.review') && (request()->query('from') === 'approvals' || auth()->user()?->hasRole('Manager'))) {
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status Dokumen')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status Pengajuan')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Menunggu Persetujuan',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                                default => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('nomor_memo')
                            ->label('Nomor Memo')
                            ->badge()
                            ->color('gray')
                            ->placeholder('Nomor dokumen belum terbit')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('date')
                            ->label('Tanggal Memo')
                            ->date('d F Y')
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('rejection_notes')
                            ->label('Alasan Penolakan')
                            ->state(function (Memo $record) {
                                $step = \App\Models\ApprovalRequest::where('document_type', Memo::class)
                                    ->where('document_id', $record->id)
                                    ->first()
                                    ?->steps()
                                    ->where('status', 'rejected')
                                    ->latest('updated_at')
                                    ->first();
                                return $step?->notes;
                            })
                            ->visible(fn (Memo $record) => $record->status === 'rejected')
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('Informasi MEMO')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    // ->description('Detail penerima, pengaju, dan perihal memo.')
                    ->schema([
                        Infolists\Components\TextEntry::make('to_recipient')
                            ->label('Kepada')
                            ->icon('heroicon-m-user')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Dari')
                            ->icon('heroicon-m-user-circle')
                            ->weight('bold'),
                            // ->helperText(fn (Memo $record) => $record->creator?->position?->name),
                        Infolists\Components\TextEntry::make('department.name')
                            ->label('Unit Kerja')
                            ->icon('heroicon-m-building-office-2'),
                        Infolists\Components\TextEntry::make('agenda')
                            ->label('Agenda')
                            ->icon('heroicon-m-tag')
                            ->weight('bold')
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('content')
                            ->label('Uraian/Latar Belakang')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2]),

                Infolists\Components\Section::make('Rincian Penugasan')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('info')
                    // ->description('Informasi kegiatan tugas lapangan dan pegawai yang ditugaskan.')
                    ->visible(fn (Memo $record) => $record->assignment_start_date || $record->location || !empty($record->participants))
                    ->schema([
                        Infolists\Components\TextEntry::make('assignment_period')
                            ->label('Waktu Penugasan')
                            ->icon('heroicon-m-calendar-days')
                            ->state(function (Memo $record) {
                                if (!$record->assignment_start_date) return '-';
                                $start = \Carbon\Carbon::parse($record->assignment_start_date)->locale('id')->translatedFormat('d F Y');
                                if ($record->assignment_end_date && $record->assignment_start_date != $record->assignment_end_date) {
                                    $end = \Carbon\Carbon::parse($record->assignment_end_date)->locale('id')->translatedFormat('d F Y');
                                    return "{$start} s/d {$end}";
                                }
                                return $start;
                            }),
                        Infolists\Components\TextEntry::make('location')
                            ->label('Tempat / Lokasi Kegiatan')
                            ->icon('heroicon-m-map-pin'),
                        Infolists\Components\ViewEntry::make('participants_detail')
                            ->label('Daftar Pegawai yang Ditugaskan')
                            ->view('filament.infolists.memo-participants')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2]),

                Infolists\Components\Section::make('Riwayat & Jejak Persetujuan')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('success')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\ViewEntry::make('approval_trail')
                            ->label('')
                            ->view('filament.infolists.memo-approval-trail')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Memo')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description('Lengkapi identitas tujuan, tanggal pembuatan, serta uraian isi memo internal.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('nomor_memo')
                            ->label('Nomor Memo / Nota Dinas')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $context): bool => $context === 'edit')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('to_recipient')
                            ->label('Kepada')
                            ->prefixIcon('heroicon-m-user-group')
                            ->placeholder('Contoh: Manajer Unit MESI / Seluruh Tim Proyek')
                            ->helperText('Jabatan atau pihak penerima memo dinas.')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal Memo Dibuat')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->helperText('Tanggal resmi penerbitan memo.')
                            ->required()
                            ->default(now()),
                        Forms\Components\RichEditor::make('content')
                            ->label('Uraian/Latar Belakang')
                            ->placeholder('Tuliskan latar belakang, maksud, dan rincian isi memo...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'undo',
                                'redo',
                            ])
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Detail Penugasan Perjalanan Dinas')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('info')
                    ->description('Lengkapi jadwal, agenda, lokasi kegiatan, dan daftar peserta yang ditugaskan.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('Agenda & Lokasi Kegiatan')
                            ->schema([
                                Forms\Components\TextInput::make('agenda')
                                    ->label('Agenda Kegiatan')
                                    ->prefixIcon('heroicon-m-clipboard-document-list')
                                    ->placeholder('Contoh: Sinkronisasi Data Aset & Inspeksi Lapangan')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('location')
                                    ->label('Tempat/Lokasi Tujuan')
                                    ->prefixIcon('heroicon-m-map-pin')
                                    ->placeholder('Contoh: Surabaya / Area Operasi Lampung')
                                    ->maxLength(255),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),

                        Forms\Components\Fieldset::make('Rentang Waktu & Peserta Penugasan')
                            ->schema([
                                Forms\Components\DatePicker::make('assignment_start_date')
                                    ->label('Tanggal Mulai Penugasan')
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->closeOnDateSelection()
                                    ->placeholder('Pilih tanggal mulai'),
                                Forms\Components\DatePicker::make('assignment_end_date')
                                    ->label('Tanggal Selesai Penugasan')
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->closeOnDateSelection()
                                    ->placeholder('Pilih tanggal selesai'),
                                Forms\Components\Select::make('participants')
                                    ->label('Peserta Penugasan')
                                    ->prefixIcon('heroicon-m-users')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih satu atau lebih pegawai yang ditugaskan...')
                                    ->options(function () {
                                        $user = auth()->user();

                                        if ($user && ($user->hasRole('Superadmin') || $user->hasRole('Admin'))) {
                                            return \App\Models\Employee::with('department')
                                                ->get()
                                                ->mapWithKeys(function ($emp) {
                                                    $unit = $emp->department?->name ?? 'Tanpa Unit';
                                                    return [$emp->id => "{$emp->name} ({$emp->identity_number}) - {$unit}"];
                                                });
                                        }

                                        $myEmployee = $user?->employee;
                                        $departmentId = $myEmployee?->department_id;

                                        if (! $departmentId) {
                                            return \App\Models\Employee::with('department')
                                                ->get()
                                                ->mapWithKeys(fn ($e) => [$e->id => "{$e->name} ({$e->identity_number})"]);
                                        }

                                        return \App\Models\Employee::where('department_id', $departmentId)
                                            ->get()
                                            ->mapWithKeys(function ($emp) {
                                                return [$emp->id => "{$emp->name} ({$emp->identity_number})"];
                                            });
                                    })
                                    ->helperText(function () {
                                        $user = auth()->user();
                                        if ($user && ($user->hasRole('Superadmin') || $user->hasRole('Admin'))) {
                                            return 'Super Admin dapat memilih peserta dari semua unit kerja.';
                                        }
                                        $unitName = $user?->employee?->department?->name;
                                        return $unitName
                                            ? "Unit {$unitName}: Menampilkan daftar pegawai dari unit kerja {$unitName}."
                                            : 'Pilih pegawai yang ditugaskan dalam perjalanan dinas ini.';
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),

                        Forms\Components\Hidden::make('status')
                            ->default('pending'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('agenda')
                        ->label('Agenda')
                        ->searchable()
                        ->weight('bold')
                        ->wrap(),
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->grow(false)
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'draft' => 'Draf',
                            'pending' => 'Menunggu Persetujuan',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                            default => ucfirst($state),
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'draft' => 'gray',
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('nomor_memo')
                                ->description('Nomor Memo', position: 'above')
                                ->placeholder('Nomor dokumen belum terbit'),
                            Tables\Columns\TextColumn::make('creator.name')
                                ->description('Dari (Pengaju)', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('to_recipient')
                                ->description('Kepada', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('date')
                                ->description('Tanggal Memo', position: 'above')
                                ->date('d M Y'),
                            Tables\Columns\TextColumn::make('dinas_period')
                                ->description('Jadwal Penugasan Dinas', position: 'above')
                                ->state(function (Memo $record) {
                                    if (! $record->assignment_start_date) return '-';
                                    $start = \Carbon\Carbon::parse($record->assignment_start_date)->format('d M Y');
                                    $end = $record->assignment_end_date ? \Carbon\Carbon::parse($record->assignment_end_date)->format('d M Y') : '-';
                                    return "{$start} s/d {$end}";
                                }),
                            Tables\Columns\TextColumn::make('location')
                                ->description('Tempat / Lokasi', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('department.name')
                                ->description('Departemen', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Dibuat Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                \App\Filament\Admin\Support\RecapTableSupport::monthYearFilter('date'),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->url(fn (Memo $record) => MemoResource::getUrl('review', ['record' => $record]))
                    ->visible(fn (Memo $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('view_review')
                    ->label('Lihat')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->url(fn (Memo $record) => MemoResource::getUrl('review', ['record' => $record]))
                    ->visible(fn (Memo $record) => $record->status !== 'pending'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (Memo $record) => route('memos.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Memo $record) => $record->status === 'approved'),
                Tables\Actions\EditAction::make()
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Memo $record) => static::canEdit($record)),
                Tables\Actions\DeleteAction::make('delete')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->button()
                    ->size('sm')
                    ->color('danger')
                    ->visible(fn (Memo $record) => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalHeading(fn (Memo $record): string => $record->status === 'approved'
                        ? 'Hapus Memo yang Sudah Disetujui?'
                        : 'Hapus Dokumen Memo?')
                    ->modalDescription(fn (Memo $record): string => $record->status === 'approved'
                        ? 'Memo ini telah disetujui (Approved). Menghapus Memo ini akan otomatis menghapus SPPD, Biaya SPPD, dan Reimbursement terkait. Wajib sertakan alasan penghapusan di bawah ini.'
                    : 'Menghapus Memo ini akan otomatis menghapus SPPD, Biaya SPPD, dan Reimbursement terkait (jika ada).')
                    ->modalSubmitActionLabel(fn (Memo $record): string => $record->status === 'approved'
                        ? 'Ya, Hapus'
                        : 'Ya, Hapus')
                    ->form(fn (Memo $record): array => $record->status === 'approved'
                        ? [
                            Forms\Components\Textarea::make('deletion_reason')
                                ->label('Alasan Penghapusan Memo')
                                ->placeholder('Wajib diisi: Jelaskan secara rinci alasan penghapusan Memo yang sudah disetujui ini...')
                                ->required()
                                ->minLength(5)
                                ->rows(3),
                        ]
                        : [
                            Forms\Components\Textarea::make('deletion_reason')
                                ->label('Alasan Penghapusan Memo (Audit Trail)')
                                ->placeholder('Opsional: Tambahkan catatan/alasan penghapusan untuk Audit Trail...')
                                ->rows(2),
                        ])
                    ->action(function (Memo $record, array $data): void {
                        $wasApproved = $record->status === 'approved';
                        $record->deleteWithAudit(auth()->user(), $data['deletion_reason'] ?? null);

                        \Filament\Notifications\Notification::make()
                            ->title($wasApproved ? 'Memo yang sudah disetujui berhasil dihapus ' : 'Memo berhasil dihapus')
                            ->body('Dokumen Memo beserta SPPD, Biaya SPPD, dan Reimbursement terkait telah dihapus.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListMemos::route('/'),
            'create' => Pages\CreateMemo::route('/create'),
            'review' => Pages\ReviewMemo::route('/{record}/review'),
            'edit' => Pages\EditMemo::route('/{record}/edit'),
        ];
    }
}
