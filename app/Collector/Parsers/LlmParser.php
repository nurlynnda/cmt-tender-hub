<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use App\Collector\TenderPatch;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/** Port of tms-v2 scrapers/llm/parseListing.ts, parseResults.ts and parseDetail.ts. */
final class LlmParser
{
    public const AGENCY = 'Lembaga Lebuhraya Malaysia (LLM)';

    /** @return list<array{source_id:string,source_url:string}> */
    public static function listing(string $html): array
    {
        $links = [];
        Dom::load($html)->filter('a[href*="/swasta/tender_detail/"]')->each(function (Crawler $a) use (&$links) {
            $href = explode('#', (string) $a->attr('href'))[0];
            if (preg_match('#/swasta/tender_detail/(\d+)/?#', $href, $m) && ! isset($links[$m[1]])) {
                $links[$m[1]] = ['source_id' => $m[1], 'source_url' => $href];
            }
        });

        return array_values($links);
    }

    /** @return list<array{source_id:string,source_url:string,winner:?array}> */
    public static function results(string $html): array
    {
        $rows = [];
        Dom::load($html)->filter('#tender-table-head table tr')->each(function (Crawler $row) use (&$rows) {
            $cells = $row->filter('td');
            if ($cells->count() < 3) {
                return;
            }
            $link = $cells->eq(0)->filter('a');
            $href = trim(explode('#', trim($link->count() ? (string) $link->attr('href') : ''))[0]);
            if (! preg_match('#/swasta/tender_detail/(\d+)/?#', $href, $m) || isset($rows[$m[1]])) {
                return;
            }
            $name = Dom::text($cells->eq(1));
            $rows[$m[1]] = [
                'source_id' => $m[1],
                'source_url' => $href,
                'winner' => $name !== '' ? ['name' => $name, 'price_sen' => Text::rmPriceSen(Dom::text($cells->eq(2)))] : null,
            ];
        });

        return array_values($rows);
    }

    public static function detail(string $html, string $sourceId, string $sourceUrl, string $status, string $scrapedAt): ?TenderPatch
    {
        $panel = Dom::load($html)->filter('#tender-table-head');
        if ($panel->count() === 0) {
            return null;
        }
        $title = Dom::text($panel->filter('header')->first());
        if ($title === '') {
            return null;
        }

        $raw = ['Tajuk' => $title];
        $panel->filter('table.tender-content tr')->each(function (Crawler $row) use (&$raw) {
            $cells = $row->filter('td');
            if ($cells->count() < 2) {
                return;
            }
            $label = Dom::text($cells->eq(0));
            if ($label !== '') {
                $raw[$label] = Dom::text($cells->eq(1));
            }
        });

        $codeText = ($raw['Tender / Sebutharga Adalah Dipelawa kepada'] ?? '').' '.($raw['Syarat Pendaftaran'] ?? '');

        try {
            return TenderPatch::make([
                'reference_no' => preg_match('/NO\.?\s*SEBUT\s*HARGA:?\s*([^\s)]+)/i', $title, $m) ? Text::clean($m[1]) : '',
                'title' => $title,
                'status' => $status,
                'procurement_type' => self::type($raw['Jenis'] ?? ''),
                'scraped_at' => $scrapedAt,
                'source' => 'llm', 'source_id' => $sourceId, 'source_url' => $sourceUrl,
                'agency' => self::AGENCY,
                'category' => ($raw['Kategori'] ?? '') ?: null,
                'field_codes' => self::fieldCodes($codeText),
                'advertised_date' => Text::dotted($raw['Tarikh Mula Jualan Dokumen'] ?? null),
                'closing_date' => Text::isoPrefix($raw['Tarikh dan Waktu Tutup'] ?? null),
                'events' => self::siteVisit($raw['Lawatan Tapak'] ?? null, (bool) preg_match('/taklimat/i', $codeText)),
                'raw' => $raw,
            ]);
        } catch (InvalidArgumentException $e) {
            Log::warning("[llm] skipping invalid detail page {$sourceUrl}: ".$e->getMessage());

            return null;
        }
    }

    /** @return list<string> */
    public static function fieldCodes(string $text): array
    {
        $codes = [];
        preg_match_all('/kod\s*bidang\s*:?\s*/i', $text, $markers, PREG_OFFSET_CAPTURE);
        foreach ($markers[0] as [$marker, $offset]) {
            $rest = substr($text, $offset + strlen($marker));
            while (preg_match('/^(\d{5,7})(?:\s*-\s*\([^)]*\))?\s*(?:,|atau|dan|\/)?\s*/i', $rest, $c)) {
                $codes[] = $c[1];
                $rest = substr($rest, strlen($c[0]));
            }
        }

        return array_values(array_unique($codes));
    }

    private static function type(string $jenis): ?string
    {
        $v = strtolower(trim($jenis));
        if ($v === 'tender') {
            return 'tender';
        }

        return str_contains($v, 'sebut') ? 'quotation' : null;
    }

    private static function siteVisit(?string $text, bool $mentionsBriefing): array
    {
        if ($text === null || $text === '-') {
            return [];
        }
        $date = preg_match('/Tarikh:\s*([\d-]+)/', $text, $d) ? Text::dashed($d[1]) : null;
        $address = preg_match('/Tempat:\s*(.*?)\s*Masa:/', $text, $a) ? Text::clean($a[1]) : '';
        $address = ($address !== '' && $address !== '-') ? $address : null;
        if ($date === null && $address === null) {
            return [];
        }

        return [['label' => $mentionsBriefing ? 'Taklimat & Lawatan Tapak' : 'Lawatan Tapak', 'date' => $date, 'address' => $address]];
    }
}
