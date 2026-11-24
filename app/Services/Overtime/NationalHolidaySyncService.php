<?php

namespace App\Services\Overtime;

use App\Models\NationalHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NationalHolidaySyncService
{
    public const GOOGLE_INDONESIA_ICS_URL = 'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics';

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function syncYear(int $year, ?string $icsUrl = null): array
    {
        $url = $icsUrl ?? self::GOOGLE_INDONESIA_ICS_URL;
        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Gagal mengunduh kalender Google Indonesia (HTTP {$response->status()}).");
        }

        $events = $this->parseIcsEvents($response->body(), $year);

        $created = 0;
        $updated = 0;

        foreach ($events as $event) {
            $existing = NationalHoliday::where('holiday_date', $event['date'])->first();

            if ($existing) {
                $existing->update([
                    'name' => $event['name'],
                    'notes' => $event['notes'],
                ]);
                $updated++;
            } else {
                NationalHoliday::create([
                    'holiday_date' => $event['date'],
                    'name' => $event['name'],
                    'is_active' => true,
                    'notes' => $event['notes'],
                ]);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($events),
        ];
    }

    /**
     * @return array<int, array{date: string, name: string, notes: string}>
     */
    public function parseIcsEvents(string $icsContent, int $year): array
    {
        // Unfold RFC 5545 folded lines (CRLF + space/tab or LF + space/tab)
        $normalized = str_replace(["\r\n ", "\r\n\t", "\n ", "\n\t"], '', $icsContent);
        $lines = preg_split('/\r\n|\r|\n/', $normalized) ?: [];

        $events = [];
        $inEvent = false;
        $dtStart = null;
        $summary = null;
        $description = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === 'BEGIN:VEVENT') {
                $inEvent = true;
                $dtStart = null;
                $summary = null;
                $description = null;
                continue;
            }

            if ($trimmed === 'END:VEVENT') {
                if ($inEvent && $dtStart && $summary) {
                    $date = $this->parseIcsDate($dtStart);
                    if ($date && (int) $date->year === $year) {
                        $dateKey = $date->toDateString();
                        $cleanSummary = $this->unescapeIcsText($summary);
                        $cleanDesc = $description ? $this->unescapeIcsText($description) : 'Sumber: Google Calendar Indonesia';

                        // Keep first or merge distinct names on same date
                        if (! isset($events[$dateKey])) {
                            $events[$dateKey] = [
                                'date' => $dateKey,
                                'name' => $cleanSummary,
                                'notes' => $cleanDesc,
                            ];
                        } elseif (! str_contains($events[$dateKey]['name'], $cleanSummary)) {
                            $events[$dateKey]['name'] .= ' / ' . $cleanSummary;
                        }
                    }
                }
                $inEvent = false;
                continue;
            }

            if (! $inEvent) {
                continue;
            }

            if (str_starts_with($trimmed, 'DTSTART')) {
                $parts = explode(':', $trimmed, 2);
                $dtStart = $parts[1] ?? null;
            } elseif (str_starts_with($trimmed, 'SUMMARY:')) {
                $summary = substr($trimmed, 8);
            } elseif (str_starts_with($trimmed, 'DESCRIPTION:')) {
                $description = substr($trimmed, 12);
            }
        }

        ksort($events);

        return array_values($events);
    }

    protected function parseIcsDate(string $value): ?Carbon
    {
        $clean = trim($value);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $clean, $matches)) {
            return Carbon::createFromDate((int) $matches[1], (int) $matches[2], (int) $matches[3])->startOfDay();
        }

        return null;
    }

    protected function unescapeIcsText(string $text): string
    {
        return str_replace(['\\,', '\\;', '\\n', '\\N', '\\\\'], [',', ';', ' ', ' ', '\\'], trim($text));
    }
}
