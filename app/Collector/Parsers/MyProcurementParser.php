<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use App\Collector\TenderPatch;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/** Port of tms-v2 scrapers/myprocurement/parseListing.ts and parseResults.ts. */
final class MyProcurementParser
{
    private const SOURCE = 'myprocurement';

    /** @return list<TenderPatch> */
    public static function listing(string $html, string $status, string $procurementType, string $scrapedAt): array
    {
        return self::eachCard($html, function (Crawler $card) use ($status, $procurementType, $scrapedAt) {
            $base = self::identity($card);
            if ($base === null) {
                return null;
            }
            [$sourceId, $title, $sourceUrl, $raw, $referenceNo] = $base;

            if (preg_match('/Tarikh Pelawaan:\s*([\d\/]+)/', $card->text('', false), $m)) {
                $raw['Tarikh Pelawaan'] = $m[1];
            }

            $events = [];
            $card->filter('table tr')->each(function (Crawler $row) use (&$events) {
                $cells = $row->filter('td');
                if ($cells->count() < 4) {
                    return;
                }
                $address = Dom::text($cells->eq(3));
                $events[] = [
                    'label' => Dom::text($cells->eq(1)),
                    'date' => Text::ddmmyyyy(Dom::text($cells->eq(2))),
                    'address' => $address !== '' ? $address : null,
                ];
            });

            return [
                'reference_no' => $referenceNo, 'title' => $title, 'status' => $status,
                'procurement_type' => $procurementType, 'scraped_at' => $scrapedAt,
                'source' => self::SOURCE, 'source_id' => $sourceId, 'source_url' => $sourceUrl,
                'ministry' => ($raw['Kementerian'] ?? '') ?: null,
                'agency' => ($raw['Agensi'] ?? '') ?: null,
                'category' => ($raw['Kategori Perolehan'] ?? '') ?: null,
                'field_codes' => Text::fieldCodes($raw['Kod Bidang'] ?? null),
                'advertised_date' => Text::ddmmyyyy($raw['Tarikh Pelawaan'] ?? null),
                'closing_date' => Text::ddmmyyyy($raw['Tarikh Tutup Pelawaan'] ?? null),
                'indicative_price_sen' => Text::rmPriceSen($raw['Harga Indikatif Jabatan'] ?? null),
                'events' => $events,
                'raw' => $raw,
            ];
        });
    }

    /** @return list<TenderPatch> */
    public static function results(string $html, string $procurementType, string $scrapedAt): array
    {
        return self::eachCard($html, function (Crawler $card) use ($procurementType, $scrapedAt) {
            $base = self::identity($card);
            if ($base === null) {
                return null;
            }
            [$sourceId, $title, $sourceUrl, $raw, $referenceNo] = $base;

            // The header row has <th> cells, so "at least 3 <td>" skips it naturally.
            $winners = [];
            $card->filter('table tr')->each(function (Crawler $row) use (&$winners) {
                $cells = $row->filter('td');
                if ($cells->count() < 3) {
                    return;
                }
                $name = Dom::text($cells->eq(1));
                if ($name === '') {
                    return;
                }
                $winners[] = ['name' => $name, 'price_sen' => Text::rmPriceSen('RM '.Dom::text($cells->eq(2)))];
            });

            return [
                'reference_no' => $referenceNo, 'title' => $title, 'status' => 'closed',
                'procurement_type' => $procurementType, 'scraped_at' => $scrapedAt,
                'source' => self::SOURCE, 'source_id' => $sourceId, 'source_url' => $sourceUrl,
                'ministry' => ($raw['Kementerian'] ?? '') ?: null,
                'agency' => ($raw['Agensi'] ?? '') ?: null,
                'category' => ($raw['Kategori Perolehan'] ?? '') ?: null,
                'winners' => $winners,
                'raw' => $raw,
            ];
        });
    }

    /** @return array{0:string,1:string,2:string,3:array<string,string>,4:string}|null */
    private static function identity(Crawler $card): ?array
    {
        if (! preg_match("/select-procurement'?,?\s*\{\s*id:\s*(\d+)/", $card->html(''), $m)) {
            return null;
        }
        $link = $card->filter('div.font-bold.text-primary a')->first();
        $title = Dom::text($link);
        $sourceUrl = $link->count() ? (string) $link->attr('href') : '';
        if ($title === '' || $sourceUrl === '') {
            return null;
        }

        $raw = [];
        $card->filter('div.font-bold.align-top')->each(function (Crawler $label) use (&$raw) {
            $name = preg_replace('/:$/', '', Dom::text($label));
            $next = $label->nextAll()->first();
            $value = ($next->count() && $next->nodeName() === 'div') ? Dom::text($next) : '';
            if ($name !== '') {
                $raw[$name] = $value;
            }
        });

        $referenceNo = '';
        $card->filter('span.font-bold')->each(function (Crawler $span) use (&$raw, &$referenceNo) {
            $label = Dom::text($span);
            if (! str_starts_with($label, 'No.')) {
                return;
            }
            $parentText = Dom::nodeText($span->getNode(0)->parentNode);
            $after = substr($parentText, strpos($parentText, $label) + strlen($label));
            $referenceNo = Text::clean(preg_replace('/^:/', '', $after));
            $raw[$label] = $referenceNo;
        });

        return [$m[1], $title, $sourceUrl, $raw, $referenceNo];
    }

    /** @return list<TenderPatch> */
    private static function eachCard(string $html, callable $build): array
    {
        $patches = [];
        Dom::load($html)->filter('div[x-data]')->each(function (Crawler $card) use ($build, &$patches) {
            if (! str_contains((string) $card->attr('x-data'), 'selected')) {
                return; // pagination wrapper etc.
            }
            $candidate = $build($card);
            if ($candidate === null) {
                return;
            }
            try {
                $patches[] = TenderPatch::make($candidate);
            } catch (InvalidArgumentException $e) {
                Log::warning('[myprocurement] skipping invalid card: '.$e->getMessage());
            }
        });

        return $patches;
    }
}
