@php
    $record = $getRecord();
    $participants = $record->participants ?? [];
    $participantsList = [];
    if (!empty($participants) && is_array($participants)) {
        foreach ($participants as $p) {
            $emp = is_numeric($p)
                ? \App\Models\Employee::with(['position', 'department'])->find($p)
                : \App\Models\Employee::with(['position', 'department'])->where('name', $p)->orWhere('identity_number', $p)->first();
            if ($emp) {
                $participantsList[] = $emp;
            }
        }
    }
@endphp

@if(count($participantsList) > 0)
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                <tr>
                    <th class="w-12 px-4 py-3">No</th>
                    <th class="px-4 py-3">Nama Pegawai & NIP</th>
                    <th class="px-4 py-3">Jabatan & Unit Kerja</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($participantsList as $index => $emp)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-900 dark:text-white">{{ $emp->name }}</div>
                            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">NIP: {{ $emp->identity_number }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                            <div>{{ $emp->position?->name ?? 'Staff' }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $emp->department?->name ?? '-' }}</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">Tidak ada peserta penugasan yang didaftarkan.</p>
@endif
