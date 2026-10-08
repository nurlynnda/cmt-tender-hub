<?php

namespace App\Livewire;

use App\Actions\Tenders\SaveCosting;
use App\Costing\{CostingCalculator, CostingForm, CostingImport};
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{CostingLine, Tender};
use App\Support\Percent;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/** The Costing tab. Holds unsaved edits on screen; one Save writes the whole costing. */
class TenderCosting extends Component
{
    public Tender $tender;
    #[Reactive] public int $version;
    /** Display hint only — SaveCosting checks permission itself. */
    public bool $canEdit = false;

    public string $defaultMargin = '20';
    public string $override = '';
    public array $lines = [];
    public bool $unsaved = false;
    public string $importText = '';
    public array $importErrors = [];
    public bool $showImport = false;
    public ?string $conflict = null;

    /** Only these count as costing edits (the page also pushes a new $version down after saves). */
    private const EDITABLE = ['defaultMargin', 'override', 'lines'];

    public function mount(): void
    {
        $state = CostingForm::fromTender($this->tender);
        $this->defaultMargin = $state['defaultMargin'];
        $this->override = $state['override'];
        $this->lines = $state['lines'];
    }

    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        if (in_array($parts[0], self::EDITABLE, true)) {
            $this->markDirty();
        }
        // A new margin means "work the price out again". A typed price leaves the margin box alone (its own
        // margin shows as the "from price" note), so clearing the price returns to the price from the margin.
        if ($parts[0] === 'lines' && ($parts[2] ?? '') === 'margin' && isset($this->lines[(int) $parts[1]])) {
            $this->lines[(int) $parts[1]]['unit_price'] = '';
        }
    }

    public function addLine(): void
    {
        $this->lines[] = CostingForm::blankLine($this->defaultBp());
        $this->markDirty();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        $this->resetValidation();
        $this->markDirty();
    }

    public function moveLine(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (isset($this->lines[$i], $this->lines[$j])) {
            [$this->lines[$i], $this->lines[$j]] = [$this->lines[$j], $this->lines[$i]];
            $this->resetValidation();
            $this->markDirty();
        }
    }

    public function addSubItem(int $i): void
    {
        if (isset($this->lines[$i])) {
            $this->lines[$i]['sub_items'][] = CostingForm::blankSubItem();
            $this->markDirty();
        }
    }

    public function removeSubItem(int $i, int $j): void
    {
        unset($this->lines[$i]['sub_items'][$j]);
        $this->lines[$i]['sub_items'] = array_values($this->lines[$i]['sub_items'] ?? []);
        $this->resetValidation();
        $this->markDirty();
    }

    public function applyDefaultToAll(): void
    {
        $this->validateOnly('defaultMargin', CostingForm::rules(), [], CostingForm::attributes());
        foreach (array_keys($this->lines) as $i) {
            $this->lines[$i]['margin'] = $this->defaultMargin;
            $this->lines[$i]['unit_price'] = '';
        }
        $this->markDirty();
    }

    public function resetOverride(): void
    {
        $this->override = '';
        $this->markDirty();
    }

    public function openImport(): void
    {
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->showImport = false;
        $this->importErrors = [];
    }

    public function import(): void
    {
        $parsed = CostingImport::parse($this->importText);
        foreach ($parsed['rows'] as $row) {
            $this->lines[] = array_merge(CostingForm::blankLine($this->defaultBp()), $row);
        }
        $this->importErrors = $parsed['errors'];
        $this->importText = '';
        $this->showImport = $parsed['errors'] !== [];
        if ($parsed['rows'] !== []) {
            $this->markDirty();
        }
    }

    public function save(): void
    {
        $this->validate(CostingForm::rules(), [], CostingForm::attributes());
        try {
            $fresh = app(SaveCosting::class)->handle(auth()->user(), $this->tender, $this->version, CostingForm::toData($this->state()));
        } catch (StaleTenderException|InvalidTenderTransition $e) {
            $this->conflict = $e->getMessage(); // the edits stay on screen

            return;
        }
        $this->tender = $fresh;
        $this->conflict = null;
        $this->unsaved = false;
        $this->dispatch('costing-dirty', dirty: false);
        $this->dispatch('costing-saved', version: $fresh->version);
    }

    private function state(): array
    {
        return ['defaultMargin' => $this->defaultMargin, 'override' => $this->override, 'lines' => $this->lines];
    }

    private function markDirty(): void
    {
        if (! $this->unsaved) {
            $this->unsaved = true;
            $this->dispatch('costing-dirty', dirty: true);
        }
    }

    private function defaultBp(): int
    {
        return CostingForm::toData(['defaultMargin' => $this->defaultMargin], lenient: true)['default_margin_bp'];
    }

    public function render()
    {
        // The page may have saved other changes (e.g. the estimated value) since this tab loaded.
        if ($this->tender->version !== $this->version) {
            $this->tender = $this->tender->fresh();
        }
        $data = CostingForm::toData($this->state(), lenient: true);

        return view('livewire.tender-costing', [
            'summary' => CostingCalculator::summary($data['lines'], $data['bid_price_override_sen'], $this->tender->estimated_value_sen),
            'editable' => $this->canEdit && ! $this->tender->isLocked(),
            'target' => Percent::format(CostingCalculator::COMPANY_TARGET_MARGIN_BP, 0),
            'vendors' => CostingLine::query()->whereNotNull('vendor')->distinct()->orderBy('vendor')->limit(200)->pluck('vendor'),
        ]);
    }
}
