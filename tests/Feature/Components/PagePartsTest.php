<?php

use Illuminate\Support\Facades\Blade;

it('prints a page title with its subtitle and action buttons', function () {
    $html = Blade::render('<x-page-heading title="In Progress Tenders" subtitle="Tenders being worked on"><x-slot:actions><button>Go</button></x-slot:actions></x-page-heading>');

    expect($html)->toContain('<h1')->toContain('In Progress Tenders')->toContain('Tenders being worked on')->toContain('<button>Go</button>');
});

it('shows how many filters are on and opens the panel when any are', function () {
    $closed = Blade::render('<x-filter-bar :count="0" placeholder="Search tenders">PANEL</x-filter-bar>');
    $open = Blade::render('<x-filter-bar :count="2" placeholder="Search tenders">PANEL</x-filter-bar>');

    expect($closed)->toContain('x-data="{ open: false }"')->not->toContain('data-filter-count')->toContain('Filter by column')->toContain('PANEL')
        ->and($open)->toContain('x-data="{ open: true }"')->toContain('data-filter-count="2"');
});

it('offers My tenders only where the list supports it, pressed when on', function () {
    expect(Blade::render('<x-filter-bar :count="0">x</x-filter-bar>'))->not->toContain('My tenders')
        ->and(Blade::render('<x-filter-bar :count="1" :mine="true">x</x-filter-bar>'))->toContain('My tenders')->toContain('aria-pressed="true"');
});

it('wraps a table in a card and marks it resizable', function () {
    expect(Blade::render('<x-data-table resizable="list-done"><tbody></tbody></x-data-table>'))
        ->toContain('data-resizable="list-done"')->toContain('overflow-x-auto');
});

it('lets many page buttons wrap onto a second line instead of widening a phone screen', function () {
    $html = (new Illuminate\Pagination\LengthAwarePaginator(range(1, 25), 1275, 25, 1))->links('pagination.pager')->toHtml();

    expect($html)->toContain('aria-label="Pages"')->toMatch('/<nav class="[^"]*flex-wrap[^"]*" aria-label="Pages"/');
});

it('shows each tender and quotation status as a coloured pill with a dot', function ($status, string $label) {
    $html = Blade::render('<x-status-pill :status="$s" />', ['s' => $status]);
    expect($html)->toContain('data-status=')->toContain('rounded-full')->toContain('h-1.5 w-1.5 rounded-full')->toContain($label);
})->with([
    [\App\Enums\TenderStatus::InProgress, 'In Progress'], [\App\Enums\TenderStatus::Dropped, 'Dropped'],
    ['sent', 'Sent'], ['expired', 'Expired'], ['revised', 'Revised'],
]);

it('prints a labelled fact, a titled card and tabs', function () {
    expect(Blade::render('<x-fact label="Tender Code">QT1</x-fact>'))->toContain('Tender Code')->toContain('QT1')
        ->and(Blade::render('<x-card title="Scope of Work" icon="tenders">Body</x-card>'))->toContain('Scope of Work')->toContain('<svg')->toContain('Body')
        ->and(Blade::render('<x-tabs><x-tab :active="true">Overview</x-tab><x-tab :active="false">Costing</x-tab></x-tabs>'))
            ->toContain('role="tablist"')->toContain('aria-selected="true"')->toContain('aria-selected="false"');
});

it('frames a dialog with a title, explanation, body and actions', function () {
    $html = Blade::render('<x-dialog title="Drop tender" subtitle="Why it matters" close="closeModal">BODY<x-slot:actions><button>Go</button></x-slot:actions></x-dialog>');
    expect($html)->toContain('role="dialog"')->toContain('Drop tender')->toContain('Why it matters')->toContain('BODY')
        ->toContain('<button>Go</button>')->toContain('wire:click="closeModal"');
});
