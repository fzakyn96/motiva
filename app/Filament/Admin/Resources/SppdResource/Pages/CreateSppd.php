<?php

namespace App\Filament\Admin\Resources\SppdResource\Pages;

use App\Filament\Admin\Resources\SppdResource;
use App\Models\Employee;
use App\Models\Sppd;
use Filament\Resources\Pages\CreateRecord;

class CreateSppd extends CreateRecord
{
    protected static string $resource = SppdResource::class;

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Simpan')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Batal')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['sppd_number'])) {
            $year = now()->format('Y');
            $month = now()->format('m');
            $employee = ! empty($data['employee_id']) ? Employee::with(['department', 'workArea'])->find($data['employee_id']) : null;
            $deptCode = $employee?->department?->code ?? 'MESI';
            $areaCode = $employee?->workArea?->code ?? 'JKT';
            $count = Sppd::whereYear('created_at', $year)->count();
            $seq = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $data['sppd_number'] = "{$seq}/SPPD/{$deptCode}/{$areaCode}/{$month}/{$year}";
        }

        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
