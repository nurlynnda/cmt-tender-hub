<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

/** HTML5 parsing (PHP 8.4's native parser via DomCrawler 8), matching how browsers and cheerio read these pages. */
final class Dom
{
    public static function load(string $html): Crawler
    {
        $crawler = new Crawler;
        $crawler->addHtmlContent($html === '' ? '<html></html>' : $html, 'UTF-8');

        return $crawler;
    }

    public static function text(Crawler $node): string
    {
        return $node->count() ? Text::clean($node->text('', false)) : '';
    }

    public static function nodeText(?DOMNode $node): string
    {
        return Text::clean($node?->textContent);
    }
}
