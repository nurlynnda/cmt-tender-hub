<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use App\Collector\TenderPatch;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/** Port of tms-v2 scrapers/span/parseListing.ts and parseDetail.ts. */
final class SpanParser
{
    public const AGENCY = 'Suruhanjaya Perkhidmatan Air Negara (SPAN)';

    /** @return list<TenderPatch> */
    public static function listing(string $html, string $scrapedAt): array
    {
        $patches = [];
        Dom::load($html)->filter('div.table-listing')->each(function (Crawler $card) use ($scrapedAt, &$patches) {
            $link = $card->filter('a')->first();
            $sourceUrl = $link->count() ? (string) $link->attr('href') : '';
            if (! preg_match('/\/tender\/view\/(\d+)/', $sourceUrl, $id)) {
                return;
            }
            $referenceNo = Dom::text($link->filter('h3')->first());
            if ($referenceNo === '') {
                return;
            }
            $fullText = Dom::text($link);
            $afterHeading = substr($fullText, strpos($fullText, $referenceNo) + strlen($referenceNo));
            $title = preg_match('/^(.*?)\s*Tarikh Iklan/', $afterHeading, $t) ? Text::clean($t[1]) : '';
            if ($title === '') {
                return;
            }
            $advertised = preg_match('/Tarikh Iklan\s*([\d-]+)/', $fullText, $a) ? $a[1] : null;
            $closing = preg_match('/Tarikh Tutup\s*([\d-]+(?:\s+\d{1,2}:\d{2}[AP]M)?)/', $fullText, $c) ? $c[1] : null;
            $badge = Dom::text($card->filter('.badge')->first());

            $raw = ['No Sebut Harga' => $referenceNo, 'Tajuk' => $title, 'Status' => $badge];
            if ($advertised !== null) {
                $raw['Tarikh Iklan'] = $advertised;
            }
            if ($closing !== null) {
                $raw['Tarikh Tutup'] = $closing;
            }

            try {
                $patches[] = TenderPatch::make([
                    'reference_no' => $referenceNo, 'title' => $title,
                    'status' => $badge === 'Diiklankan' ? 'open' : 'closed',
                    'procurement_type' => self::type($title), 'scraped_at' => $scrapedAt,
                    'source' => 'span', 'source_id' => $id[1], 'source_url' => $sourceUrl,
                    'agency' => self::AGENCY,
                    'advertised_date' => Text::isoPrefix($advertised),
                    'closing_date' => Text::isoPrefix($closing),
                    'raw' => $raw,
                ]);
            } catch (InvalidArgumentException $e) {
                Log::warning('[span] skipping invalid card: '.$e->getMessage());
            }
        });

        return $patches;
    }

    /** @return list<array{name:string,price_sen:int}> */
    public static function winners(string $html): array
    {
        $winners = [];
        Dom::load($html)->filter('tr')->each(function (Crawler $row) use (&$winners) {
            $cells = array_map(fn ($n) => Dom::nodeText($n), iterator_to_array($row->filter('td')));
            $nameIdx = array_search('Nama Pembekal', $cells, true);
            $priceIdx = array_search('Harga Tawaran', $cells, true);
            if ($nameIdx === false || $priceIdx === false) {
                return;
            }
            $name = self::valueAfter($cells, $nameIdx);
            $price = Text::rmPriceSen(self::valueAfter($cells, $priceIdx));
            if ($name === '' || $price === null) {
                return;
            }
            $winners[] = ['name' => $name, 'price_sen' => $price];
        });

        return $winners;
    }

    private static function valueAfter(array $cells, int $labelIdx): string
    {
        for ($i = $labelIdx + 1; $i < count($cells); $i++) {
            if ($cells[$i] !== '' && $cells[$i] !== ':') {
                return $cells[$i];
            }
        }

        return '';
    }

    private static function type(string $title): ?string
    {
        if (preg_match('/TENDER/i', $title)) {
            return 'tender';
        }

        return preg_match('/SEBUT\s*HARGA/i', $title) ? 'quotation' : null;
    }
}
