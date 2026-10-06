<?php

use App\Actions\Tenders\SaveCosting;
use App\Costing\CostingForm;
use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{CostingLine, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

function costingState(array $o = []): array
{
    $line = array_merge(CostingForm::blankLine(2000), ['description' => 'Server', 'unit_cost' => '100,000'], $o);

    return ['defaultMargin' => '20', 'override' => '', 'lines' => [$line]];
}

function picTender(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);

    return [$pic, Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'estimated_value_sen' => 15000000], $attrs))];
}

it('converts screen input to saved data', function () {
    $state = costingState(['quantity' => '2', 'frequency' => 'monthly', 'months' => '6', 'project_year' => '3', 'margin' => '18.5',
        'sub_items' => [array_merge(CostingForm::blankSubItem(), ['description' => 'PC', 'quantity' => '1', 'unit_cost' => '3,000'])]]);
    $state['override'] = 'RM 130,000';

    $data = CostingForm::toData($state);

    expect($data['default_margin_bp'])->toBe(2000)
        ->and($data['bid_price_override_sen'])->toBe(13000000)
        ->and($data['lines'][0])->toMatchArray(['quantity' => 2, 'frequency' => 'monthly', 'months' => 6, 'project_year' => 3,
            'unit_cost_sen' => 10000000, 'margin_bp' => 1850, 'description' => 'Server'])
        ->and($data['lines'][0]['sub_items'][0])->toMatchArray(['description' => 'PC', 'quantity' => 1, 'unit_cost_sen' => 300000]);
});

it('forces months to 1 for one-off lines, and tolerates junk in lenient mode', function () {
    expect(CostingForm::toData(costingState(['months' => '9']))['lines'][0]['months'])->toBe(1);

    $lenient = CostingForm::toData(costingState(['unit_cost' => 'abc', 'quantity' => 'x', 'margin' => '??']), lenient: true)['lines'][0];
    expect($lenient)->toMatchArray(['unit_cost_sen' => 0, 'quantity' => 1, 'margin_bp' => 2000]);
});

it('throws on junk outside lenient mode', function () {
    CostingForm::toData(costingState(['unit_cost' => 'abc']));
})->throws(InvalidArgumentException::class);

it('validates every field', function (array $bad, string $key) {
    $v = Validator::make(costingState($bad), CostingForm::rules());

    expect($v->errors()->has($key))->toBeTrue();
})->with([
    [['description' => ''], 'lines.0.description'],
    [['quantity' => '0'], 'lines.0.quantity'],
    [['months' => '0', 'frequency' => 'monthly'], 'lines.0.months'],
    [['project_year' => '8'], 'lines.0.project_year'],
    [['unit_cost' => '-5'], 'lines.0.unit_cost'],
    [['margin' => '100'], 'lines.0.margin'],
    [['quote_url' => 'javascript:alert(1)'], 'lines.0.quote_url'],
    [['frequency' => 'weekly'], 'lines.0.frequency'],
]);

it('accepts a valid costing', function () {
    expect(Validator::make(costingState(['quote_url' => 'https://drive.example.com/q.pdf']), CostingForm::rules())->passes())->toBeTrue();
});

it('saves the whole costing, replacing what was there, and logs it', function () {
    [$pic, $tender] = picTender();
    $save = app(SaveCosting::class);

    $t = $save->handle($pic, $tender, 1, CostingForm::toData(costingState(['description' => 'Old'])));
    $t = $save->handle($pic, $t, 2, CostingForm::toData(costingState()));

    expect($t->version)->toBe(3)
        ->and($t->costingLines->pluck('description')->all())->toBe(['Server'])
        ->and($t->hasCosting())->toBeTrue()
        ->and($t->costingSummary()['bid_price_sen'])->toBe(12500000)
        ->and($t->activity->first()->description)->toBe('Costing saved — bid price RM 125,000.00, margin 20.0%');
});

it('saves sub-items and the override', function () {
    [$pic, $tender] = picTender();
    $state = costingState(['sub_items' => [array_merge(CostingForm::blankSubItem(), ['description' => 'PC', 'unit_cost' => '3,000'])]]);
    $state['override'] = '9000';

    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData($state));

    expect($t->bid_price_override_sen)->toBe(900000)
        ->and($t->costingLines->first()->subItems->pluck('unit_cost_sen')->all())->toBe([300000])
        ->and($t->costingSummary()['total_cost_sen'])->toBe(300000);
});

it('logs a cleared costing when every line is removed', function () {
    [$pic, $tender] = picTender();
    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData(costingState()));
    $state = costingState();
    $state['lines'] = [];

    $t = app(SaveCosting::class)->handle($pic, $t, 2, CostingForm::toData($state));

    expect($t->costingLines)->toHaveCount(0)->and($t->activity->first()->description)->toBe('Costing cleared');
});

it('round-trips saved rows back to the screen', function () {
    [$pic, $tender] = picTender();
    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData(costingState(['margin' => '18.5'])));

    $state = CostingForm::fromTender($t);

    expect($state['defaultMargin'])->toBe('20')
        ->and($state['override'])->toBe('')
        ->and($state['lines'][0])->toMatchArray(['description' => 'Server', 'unit_cost' => '100000.00', 'margin' => '18.5', 'quantity' => '1']);
});

it('refuses staff who are not the PIC, closed tenders, and out-of-date pages', function () {
    [$pic, $tender] = picTender();
    $data = CostingForm::toData(costingState());

    expect(fn () => app(SaveCosting::class)->handle(User::factory()->create(), $tender, 1, $data))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveCosting::class)->handle($pic, $tender, 9, $data))->toThrow(StaleTenderException::class);

    $tender->update(['status' => TenderStatus::Done]);
    expect(fn () => app(SaveCosting::class)->handle($pic, $tender, 1, $data))->toThrow(InvalidTenderTransition::class);
    expect($tender->fresh()->costingLines)->toHaveCount(0);
});

it('has no costing until there is a line and a bid price above zero', function () {
    [, $tender] = picTender();
    expect($tender->hasCosting())->toBeFalse()->and($tender->costingSummary())->toBeNull();

    CostingLine::factory()->for($tender)->create(['unit_cost_sen' => 0]);
    expect($tender->fresh()->hasCosting())->toBeFalse();
});
