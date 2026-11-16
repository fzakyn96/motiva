<?php

namespace App\Services\MasterData;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportExportService
{
    /**
     * Stream a UTF-8 BOM CSV file download.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public function streamCsvDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM so Microsoft Excel renders UTF-8 characters properly
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Stream an Excel 2003 SpreadsheetML (.xls) file download.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public function streamXlsDownload(string $filename, array $headers, iterable $rows, string $sheetName = 'Data'): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $sheetName): void {
            $escape = fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
                . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
                . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
                . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
            echo '<Styles><Style ss:ID="Header"><Font ss:Bold="1"/></Style></Styles>' . "\n";
            echo '<Worksheet ss:Name="' . $escape($sheetName) . '"><Table>' . "\n";

            echo '<Row>';
            foreach ($headers as $header) {
                echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $escape($header) . '</Data></Cell>';
            }
            echo "</Row>\n";

            foreach ($rows as $row) {
                echo '<Row>';
                foreach ($row as $cell) {
                    echo '<Cell><Data ss:Type="String">' . $escape($cell) . '</Data></Cell>';
                }
                echo "</Row>\n";
            }

            echo '</Table></Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * Parse an uploaded spreadsheet (.xls SpreadsheetML/HTML, .xlsx, or .csv) from local disk.
     *
     * @return array<int, array<string, string>>
     */
    public function parseUploadedSpreadsheet(string $storedPath): array
    {
        $fullPath = Storage::disk('local')->path($storedPath);
        if (! is_file($fullPath)) {
            return [];
        }

        $content = file_get_contents($fullPath);
        if ($content === false || $content === '') {
            Storage::disk('local')->delete($storedPath);

            return [];
        }

        // 1. XLSX (ZIP signature PK\x03\x04) via OpenSpout
        if (str_starts_with($content, "PK\x03\x04") && class_exists(\OpenSpout\Reader\XLSX\Reader::class)) {
            $rawRows = [];
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($fullPath);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rawRows[] = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());
                }
                break;
            }
            $reader->close();
            Storage::disk('local')->delete($storedPath);

            return $this->mapRawRowsToAssociative($rawRows);
        }

        // 2. Excel 2003 SpreadsheetML XML (.xls)
        if (str_contains($content, 'schemas-microsoft-com:office:spreadsheet') || str_contains($content, '<Workbook')) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);
            libxml_clear_errors();

            if ($xml !== false) {
                $namespaces = $xml->getNamespaces(true);
                $ssNs = $namespaces['ss'] ?? 'urn:schemas-microsoft-com:office:spreadsheet';
                $xml->registerXPathNamespace('ss', $ssNs);

                $rawRows = [];
                $rowNodes = $xml->xpath('//ss:Worksheet[1]//ss:Table/ss:Row') ?: $xml->xpath('//Worksheet[1]//Table/Row') ?: [];
                foreach ($rowNodes as $rowNode) {
                    $cells = [];
                    $colIndex = 0;
                    $cellNodes = $rowNode->children($ssNs)->Cell ?? $rowNode->Cell ?? [];
                    foreach ($cellNodes as $cellNode) {
                        $attrs = $cellNode->attributes($ssNs);
                        if (isset($attrs['Index'])) {
                            $colIndex = max(0, ((int) $attrs['Index']) - 1);
                        }
                        while (count($cells) < $colIndex) {
                            $cells[] = '';
                        }
                        $dataNode = $cellNode->children($ssNs)->Data ?? $cellNode->Data ?? null;
                        $cells[$colIndex] = trim((string) ($dataNode ?? ''));
                        $colIndex++;
                    }
                    $rawRows[] = $cells;
                }

                Storage::disk('local')->delete($storedPath);

                return $this->mapRawRowsToAssociative($rawRows);
            }
        }

        // 3. Fallback: CSV
        return $this->parseUploadedCsv($storedPath);
    }

    /**
     * @param  array<int, array<int, string>>  $rawRows
     * @return array<int, array<string, string>>
     */
    protected function mapRawRowsToAssociative(array $rawRows): array
    {
        if (count($rawRows) < 2) {
            return [];
        }

        $headers = array_map(function ($header): string {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? '';

            return strtolower(trim(str_replace(' ', '_', $clean)));
        }, $rawRows[0]);

        $rows = [];
        foreach (array_slice($rawRows, 1) as $data) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = trim((string) ($data[$index] ?? ''));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Parse an uploaded CSV file from the local disk into associative rows and delete the temp file.
     *
     * @return array<int, array<string, string>>
     */
    public function parseUploadedCsv(string $storedPath): array
    {
        $fullPath = Storage::disk('local')->path($storedPath);
        if (! is_file($fullPath)) {
            return [];
        }

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            Storage::disk('local')->delete($storedPath);

            return [];
        }

        $delimiter = str_contains($firstLine, ';') && ! str_contains($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (! is_array($headers)) {
            fclose($handle);
            Storage::disk('local')->delete($storedPath);

            return [];
        }

        $normalizedHeaders = array_map(function ($header): string {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? '';

            return strtolower(trim(str_replace(' ', '_', $clean)));
        }, $headers);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($normalizedHeaders as $index => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = trim((string) ($data[$index] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);
        Storage::disk('local')->delete($storedPath);

        return $rows;
    }

    /**
     * Import departments from CSV rows.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importDepartments(string $storedPath): array
    {
        $rows = $this->parseUploadedSpreadsheet($storedPath);
        $defaultOrg = Organization::first() ?? Organization::create([
            'name' => 'PT Permata Karya Jasa',
            'code' => 'PKJ',
        ]);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $code = $row['kode'] ?? $row['code'] ?? '';
            $name = $row['nama_unit_kerja'] ?? $row['name'] ?? '';
            $orgName = $row['organisasi'] ?? '';

            if ($code === '' && $name === '') {
                $stats['skipped']++;
                continue;
            }

            $org = $orgName !== ''
                ? (Organization::where('name', $orgName)->orWhere('code', $orgName)->first() ?? $defaultOrg)
                : $defaultOrg;

            $existing = $code !== ''
                ? Department::where('code', $code)->first()
                : Department::where('name', $name)->first();

            if ($existing) {
                $existing->update([
                    'organization_id' => $org->id,
                    'name' => $name !== '' ? $name : $existing->name,
                    'code' => $code !== '' ? $code : $existing->code,
                ]);
                $stats['updated']++;
            } else {
                Department::create([
                    'organization_id' => $org->id,
                    'name' => $name !== '' ? $name : $code,
                    'code' => $code !== '' ? $code : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? 'DEP', 0, 10)),
                ]);
                $stats['created']++;
            }
        }

        return $stats;
    }

    /**
     * Import positions from CSV rows.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importPositions(string $storedPath): array
    {
        $rows = $this->parseUploadedSpreadsheet($storedPath);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $code = $row['kode'] ?? $row['code'] ?? '';
            $name = $row['nama_jabatan'] ?? $row['name'] ?? '';
            $level = is_numeric($row['level'] ?? null) ? (int) $row['level'] : 4;

            if ($code === '' && $name === '') {
                $stats['skipped']++;
                continue;
            }

            $existing = $code !== ''
                ? Position::where('code', $code)->first()
                : Position::where('name', $name)->first();

            if ($existing) {
                $existing->update([
                    'name' => $name !== '' ? $name : $existing->name,
                    'code' => $code !== '' ? $code : $existing->code,
                    'level' => $level,
                ]);
                $stats['updated']++;
            } else {
                Position::create([
                    'name' => $name !== '' ? $name : $code,
                    'code' => $code !== '' ? $code : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? 'POS', 0, 10)),
                    'level' => $level,
                ]);
                $stats['created']++;
            }
        }

        return $stats;
    }

    /**
     * Import employees from CSV rows.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importEmployees(string $storedPath): array
    {
        $rows = $this->parseUploadedSpreadsheet($storedPath);
        $defaultOrg = Organization::first();
        $defaultDept = Department::first();
        $defaultPos = Position::first();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $nip = $row['nip'] ?? $row['identity_number'] ?? '';
            $name = $row['nama_pegawai'] ?? $row['name'] ?? '';

            if ($nip === '' || $name === '') {
                $stats['skipped']++;
                continue;
            }

            $deptCode = $row['kode_unit_kerja'] ?? '';
            $deptName = $row['unit_kerja'] ?? '';
            $department = Department::query()
                ->when($deptCode !== '', fn ($q) => $q->where('code', $deptCode))
                ->when($deptCode === '' && $deptName !== '', fn ($q) => $q->where('name', $deptName))
                ->first() ?? $defaultDept;

            $posCode = $row['kode_jabatan'] ?? '';
            $posName = $row['jabatan'] ?? '';
            $position = Position::query()
                ->when($posCode !== '', fn ($q) => $q->where('code', $posCode))
                ->when($posCode === '' && $posName !== '', fn ($q) => $q->where('name', $posName))
                ->first() ?? $defaultPos;

            if (! $department || ! $position) {
                $stats['skipped']++;
                continue;
            }

            $managerRef = $row['atasan_langsung'] ?? '';
            $managerId = null;
            if ($managerRef !== '' && $managerRef !== '-') {
                $managerId = Employee::where('identity_number', $managerRef)
                    ->orWhere('name', $managerRef)
                    ->value('id');
            }

            $email = $row['email'] ?? '';
            $username = $row['username'] ?? '';
            $user = null;

            if ($email !== '' || $username !== '') {
                $user = User::query()
                    ->when($email !== '', fn ($q) => $q->where('email', $email))
                    ->when($email === '' && $username !== '', fn ($q) => $q->where('username', $username))
                    ->first();

                if ($user && $username !== '' && $user->username !== $username) {
                    $usernameTaken = User::where('username', $username)->where('id', '!=', $user->id)->exists();
                    if (! $usernameTaken) {
                        $user->update(['username' => $username]);
                    }
                }
            }

            $existing = Employee::where('identity_number', $nip)->first();
            $payload = [
                'organization_id' => $department->organization_id ?? $defaultOrg?->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'manager_id' => $managerId ?? $existing?->manager_id,
                'user_id' => $user?->id ?? $existing?->user_id,
                'name' => $name,
                'identity_number' => $nip,
            ];

            if ($existing) {
                $existing->update($payload);
                $stats['updated']++;
            } else {
                Employee::create($payload);
                $stats['created']++;
            }
        }

        return $stats;
    }
}

