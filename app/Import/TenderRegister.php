<?php

namespace App\Import;

use App\Enums\{TenderMode, TenderStatus, TenderType};
use App\Support\Money;
use InvalidArgumentException;

/**
 * Reads the team's tender register (CSV export of their spreadsheet) into clean rows.
 * No database access: the importer decides what to do with the rows.
 */
final class TenderRegister
{
    public const REQUIRED = ['WO Number', 'WO DATE', 'Mode', 'PIC', 'Ministry', 'PTJ Code & Name', 'QT No', 'QT Title (Full)', 'Closing Date', 'Status'];

    /** Register status → [app status, was cancelled] */
    private const STATUSES = [
        'open' => [TenderStatus::InProgress, false],
        'assigned' => [TenderStatus::InProgress, false],
        'submitted' => [TenderStatus::Done, false],
        'won' => [TenderStatus::Awarded, false],
        'lost' => [TenderStatus::Lost, false],
        'cancelled' => [TenderStatus::Lost, true],
        'drop' => [TenderStatus::Dropped, false],
    ];

    /** @return array{rows: list<array>, skipped: list<array{line:int, wo:string, reason:string}>, warnings: list<string>} */
    public function read(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RegisterFormatException("Cannot open {$path}");
        }

        try {
            $header = array_map(fn ($h) => self::clean((string) $h), fgetcsv($handle, escape: '') ?: []);
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0] ?? ''); // Excel byte-order mark
            $missing = array_values(array_diff(self::REQUIRED, $header));
            if ($missing !== []) {
                throw new RegisterFormatException('This does not look like the tender register — missing columns: '.implode(', ', $missing));
            }
            $col = array_flip($header);

            $rows = $skipped = $warnings = $seen = [];
            $line = 1;
            while (($cells = fgetcsv($handle, escape: '')) !== false) {
                $line++;
                $get = fn (string $name) => isset($col[$name]) ? self::clean((string) ($cells[$col[$name]] ?? '')) : '';
                $wo = $get('WO Number');
                if ($wo === '') {
                    continue; // blank / leftover formula rows at the bottom of the sheet
                }
                $reason = null;
                $row = $this->row($get, $line, $warnings, $reason);
                if ($reason === null && isset($seen[$wo])) {
                    $reason = 'duplicate WO number in file';
                }
                if ($reason !== null) {
                    $skipped[] = ['line' => $line, 'wo' => $wo, 'reason' => $reason];

                    continue;
                }
                $seen[$wo] = true;
                $rows[] = ['wo_number' => $wo] + $row;
            }

            return ['rows' => $rows, 'skipped' => $skipped, 'warnings' => $warnings];
        } finally {
            fclose($handle);
        }
    }

    private function row(callable $get, int $line, array &$warnings, ?string &$reason): ?array
    {
        $statusText = $get('Status');
        $mapped = self::STATUSES[strtolower($statusText)] ?? null;
        if ($mapped === null) {
            $reason = "unknown status '{$statusText}'";

            return null;
        }
        $mode = match (strtolower(str_replace([' ', '-'], '', $get('Mode')))) {
            'ep' => TenderMode::Ep,
            'nonep' => TenderMode::NonEp,
            default => null,
        };
        if ($mode === null) {
            $reason = 'no mode';

            return null;
        }
        $woDate = self::date($get('WO DATE'));
        $closing = self::date($get('Closing Date'));
        if ($woDate === null || $closing === null) {
            $reason = $woDate === null ? 'no WO date' : 'no closing date';

            return null;
        }
        $type = match (strtolower($get('Type'))) {
            'sh', 'quotation' => TenderType::Quotation,
            default => TenderType::Tender,
        };
        $ministry = $get('Ministry');
        $client = $get('PTJ Code & Name');
        $pic = $get('PIC');
        $money = function (string $name) use ($get, $line, &$warnings): ?int {
            $value = $get($name);
            try {
                return Money::parse($value === '' ? null : $value);
            } catch (InvalidArgumentException) {
                // "-", "`" and spreadsheet errors (#DIV/0!) are the sheet's usual ways of saying "none"
                if (! in_array($value, ['-', '`'], true) && ! str_starts_with($value, '#')) {
                    $warnings[] = "Line {$line}: {$name} '{$value}' is not an amount — left empty";
                }

                return null;
            }
        };

        return [
            'line' => $line,
            'wo_date' => $woDate,
            'mode' => $mode,
            'type' => $type,
            'tender_code' => $get('QT No'),
            'title' => $get('QT Title (Full)'),
            'ministry' => $ministry === '' ? null : $ministry,
            'client' => $client !== '' ? $client : $ministry,
            'publish_date' => self::date($get('Publish Date')),
            'closing_date' => $closing,
            'briefing_date' => self::date($get('Briefing date')),
            'estimated_value_sen' => $money('Indicative Price'),
            'submitted_price_sen' => $money('Submission Price'),
            'winning_price_sen' => $money('Win Price'),
            'submitted_cost_sen' => $money('Submitted Cost'),
            'pic_name' => $pic === '' ? null : $pic,
            'status' => $mapped[0],
            'was_cancelled' => $mapped[1],
        ];
    }

    /** Collapse tabs and repeated spaces; trim. */
    private static function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** m/d/yyyy → Y-m-d, or null when blank or not a real date. */
    private static function date(string $value): ?string
    {
        if (! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) || ! checkdate((int) $m[1], (int) $m[2], (int) $m[3])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    }
}
