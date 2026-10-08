<?php

use Illuminate\Support\Facades\Blade;

it('draws every icon the screens use', function (string $name) {
    expect(Blade::render('<x-icon name="'.$name.'" class="h-4 w-4" />'))
        ->toContain('<svg')->toContain('viewBox="0 0 24 24"')->toContain('h-4 w-4"')->toContain('aria-hidden="true"');
})->with(['dashboard', 'tenders', 'clock', 'award', 'check', 'staff', 'user', 'quotation', 'settings', 'search', 'plus',
    'chevronLeft', 'chevronRight', 'chevronDown', 'x', 'filter', 'logout', 'bell', 'moon', 'panel', 'calendar', 'chart', 'minus']);

it('refuses an icon name it does not know, so a typo cannot ship silently', function () {
    Blade::render('<x-icon name="nope" />');
})->throws(Illuminate\View\ViewException::class, 'Unknown icon "nope"');

it('shows initials in a circle with the full name on hover', function () {
    expect(Blade::render('<x-initials name="Ahmad Faizal" />'))->toContain('>AF</span>')->toContain('title="Ahmad Faizal"');
});
