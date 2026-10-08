<?php

namespace App\Livewire;

use App\Actions\Quotations\{AddQuotationItem, CreateProjectFromQuotation, DuplicateQuotation, MarkQuotationAccepted, MarkQuotationRejected, MarkQuotationSent, MoveQuotationBackToDraft, MoveQuotationItem, RemoveQuotationItem, ReviseQuotation, UpdateQuotation, UpdateQuotationItem};
use App\Exceptions\{InvalidQuotationTransition, QuotationIncomplete, QuotationLocked, StaleQuotation};
use App\Models\{CompanyProfile, Quotation, QuotationItem, User};
use App\Costing\{CostingCalculator, CostingForm};
use App\Rules\Percentage;
use App\Support\Percent;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\{Layout, Url};
use Livewire\Component;

/** One quotation: Details, Items, Terms, Preview (the real PDF), History. Fields save when you leave them. */
#[Layout('layouts.app')]
class QuotationPage extends Component
{
    public Quotation $quotation;
    #[Url] public string $tab = 'details';
    public int $version = 1;
    public array $form = [];
    /** Item fields keyed "i{id}". */
    public array $items = [];
    public ?string $problem = null;

    public function mount(Quotation $quotation): void
    {
        $this->quotation = $quotation;
        $this->load();
    }

    private function load(): void
    {
        $q = $this->quotation = $this->quotation->fresh(['items']);
        $this->version = $q->version;
        $this->form = [
            'quote_date' => $q->quote_date->format('Y-m-d'),
            'validity_days' => (string) $q->validity_days,
            'customer_name' => (string) $q->customer_name,
            'attention' => (string) $q->attention,
            'attention_phone' => (string) $q->attention_phone,
            'attention_email' => (string) $q->attention_email,
            'customer_address' => (string) $q->customer_address,
            'subject' => (string) $q->subject,
            'prepared_by' => (string) $q->prepared_by,
            'preparer_position' => (string) $q->preparer_position,
            'preparer_phone' => (string) $q->preparer_phone,
            'preparer_email' => (string) $q->preparer_email,
            'show_signature' => $q->show_signature,
            'show_stamp' => $q->show_stamp,
            'sst' => Percent::toInput($q->sst_bp),
            'default_margin' => Percent::toInput($q->default_margin_bp),
            'terms' => (string) $q->terms,
        ];
        $this->items = [];
        foreach ($q->items as $i) {
            $this->items["i{$i->id}"] = ['title' => $i->title, 'details' => (string) $i->details, 'sst' => $i->has_sst]
                + CostingForm::lineFromModel($i);
        }
    }

    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        if ($parts[0] === 'form' && isset($parts[1])) {
            $this->saveField($parts[1]);
        } elseif ($parts[0] === 'items' && isset($parts[1], $this->items[$parts[1]])) {
            $k = $parts[1];
            // A new margin means "work the price out again". A typed price leaves the margin alone (its own margin
            // shows as the "from price" note), so clearing the price returns to the price from the margin.
            if (($parts[2] ?? '') === 'margin') {
                $this->items[$k]['unit_price'] = '';
            }
            $this->saveItem((int) substr($k, 1));
        }
    }

    /** A sub-item row just added and not filled in yet: not checked and not saved, so it never blocks the item. */
    private static function isBlankSubItem(array $s): bool
    {
        return trim((string) ($s['description'] ?? '')) === '' && in_array(trim((string) ($s['unit_cost'] ?? '')), ['', '0', '0.00'], true)
            && trim((string) ($s['vendor'] ?? '')) === '' && trim((string) ($s['quote_url'] ?? '')) === '';
    }

    public function addSubItem(int $id): void
    {
        if (isset($this->items["i{$id}"])) {
            $this->items["i{$id}"]['sub_items'][] = CostingForm::blankSubItem(); // saved once it has a description
        }
    }

    public function removeSubItem(int $id, int $j): void
    {
        if (! isset($this->items["i{$id}"])) {
            return;
        }
        unset($this->items["i{$id}"]['sub_items'][$j]);
        $this->items["i{$id}"]['sub_items'] = array_values($this->items["i{$id}"]['sub_items'] ?? []);
        $this->saveItem($id);
    }

    /**
     * Checks and saves only the field that changed, so a mistake in one field never blocks the others
     * (and an old, switched-off preparer does not block edits until someone changes "Prepared by").
     */
    private function saveField(string $field): void
    {
        $rules = $this->formRules();
        if (! isset($rules["form.$field"])) {
            return;
        }
        $this->validateOnly("form.$field", $rules, [], $this->formAttributes());

        $value = $this->form[$field];
        $blank = fn ($v) => trim((string) $v) === '' ? null : trim((string) $v);
        $data = match ($field) {
            'sst' => ['sst_bp' => Percent::parseBp($value)],
            'default_margin' => ['default_margin_bp' => Percent::parseBp($value)],
            'validity_days', 'prepared_by' => [$field => (int) $value],
            'show_signature', 'show_stamp' => [$field => (bool) $value],
            'quote_date', 'terms' => [$field => $value],
            default => [$field => $blank($value)],
        };
        $saved = $this->run(function () use ($data, $field) {
            $this->version = app(UpdateQuotation::class)->handle(auth()->user(), $this->quotation, $this->version, $data)->version;
            if ($field === 'prepared_by') {
                $this->load(); // the new preparer's contact details replace the old ones
            }
        });
        if ($saved) {
            $this->dispatch('saved'); // the page shows "Saved ✓" briefly
        }
    }

    private function formRules(): array
    {
        return [
            'form.quote_date' => ['required', 'date_format:Y-m-d'],
            'form.validity_days' => ['required', 'integer', 'between:1,365'],
            'form.customer_name' => ['nullable', 'string', 'max:255'],
            'form.attention' => ['nullable', 'string', 'max:255'],
            'form.attention_phone' => ['nullable', 'string', 'max:50'],
            'form.attention_email' => ['nullable', 'email', 'max:255'],
            'form.customer_address' => ['nullable', 'string', 'max:1000'],
            'form.subject' => ['nullable', 'string', 'max:500'],
            'form.prepared_by' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'form.preparer_position' => ['nullable', 'string', 'max:255'],
            'form.preparer_phone' => ['nullable', 'string', 'max:50'],
            'form.preparer_email' => ['nullable', 'email', 'max:255'],
            'form.show_signature' => ['boolean'],
            'form.show_stamp' => ['boolean'],
            'form.sst' => ['required', new Percentage],
            'form.default_margin' => ['required', new Percentage],
            'form.terms' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function formAttributes(): array
    {
        return [
            'form.quote_date' => 'date', 'form.validity_days' => 'validity', 'form.attention_email' => 'attention email',
            'form.prepared_by' => 'prepared by', 'form.preparer_email' => 'email', 'form.sst' => 'SST',
            'form.default_margin' => 'default margin',
        ];
    }

    /** Checks and saves one item: customer fields, costing (cost, margin or typed price, sub-items) and the SST tick. */
    private function saveItem(int $id): void
    {
        $k = "i{$id}";
        $rules = CostingForm::lineRules("items.$k");
        unset($rules["items.$k.description"]); // an item's name is its title
        // Sub-item rules only for rows that have been filled in (a blank new row must not block the item).
        $subRules = array_filter($rules, fn ($key) => str_contains($key, '.sub_items.*.'), ARRAY_FILTER_USE_KEY);
        $rules = array_diff_key($rules, $subRules);
        $blank = [];
        foreach ($this->items[$k]['sub_items'] ?? [] as $j => $sub) {
            if (self::isBlankSubItem($sub)) {
                $blank[] = $j;

                continue;
            }
            foreach ($subRules as $key => $rule) {
                $rules[str_replace('.sub_items.*.', ".sub_items.$j.", $key)] = $rule;
            }
        }
        $this->validate($rules + [
            "items.$k.title" => ['required', 'string', 'max:255'],
            "items.$k.details" => ['nullable', 'string', 'max:2000'],
            "items.$k.sst" => ['boolean'],
        ], [], [
            "items.$k.title" => 'title', "items.$k.quantity" => 'quantity', "items.$k.unit" => 'unit', "items.$k.frequency" => 'frequency',
            "items.$k.unit_cost" => 'unit cost', "items.$k.margin" => 'margin', "items.$k.unit_price" => 'unit price',
            "items.$k.vendor" => 'vendor', "items.$k.quote_url" => 'quotation link',
            "items.$k.sub_items.*.description" => 'description', "items.$k.sub_items.*.unit" => 'unit',
            "items.$k.sub_items.*.quantity" => 'quantity', "items.$k.sub_items.*.unit_cost" => 'unit cost',
            "items.$k.sub_items.*.quote_url" => 'quotation link',
        ]);
        $row = $this->items[$k];
        $row['sub_items'] = array_diff_key($row['sub_items'] ?? [], array_flip($blank));
        $data = ['title' => $row['title'], 'details' => $row['details'], 'has_sst' => (bool) ($row['sst'] ?? true)]
            + CostingForm::lineToData($row, $this->quotation->default_margin_bp, lenient: false);
        $saved = $this->run(fn () => $this->version = app(UpdateQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version, $data)->version);
        if ($saved) {
            $this->dispatch('saved');
        }
    }

    public function addItem(): void
    {
        $this->run(fn () => app(AddQuotationItem::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function removeItem(int $id): void
    {
        $this->run(fn () => app(RemoveQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version), reload: true);
    }

    public function moveItem(int $id, int $direction): void
    {
        $this->run(fn () => app(MoveQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version, $direction), reload: true);
    }

    public function resetTerms(): void
    {
        $this->form['terms'] = (string) CompanyProfile::current()->default_terms;
        $this->saveField('terms');
    }

    public function markSent(): void
    {
        $this->run(fn () => app(MarkQuotationSent::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function markAccepted(): void
    {
        $this->run(fn () => app(MarkQuotationAccepted::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function markRejected(): void
    {
        $this->run(fn () => app(MarkQuotationRejected::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function backToDraft(): void
    {
        $this->run(fn () => app(MoveQuotationBackToDraft::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function revise(): void
    {
        $this->run(fn () => $this->redirectRoute('quotations.show', app(ReviseQuotation::class)->handle(auth()->user(), $this->quotation, $this->version)));
    }

    public function duplicate(): void
    {
        $this->run(fn () => $this->redirectRoute('quotations.show', app(DuplicateQuotation::class)->handle(auth()->user(), $this->quotation)));
    }

    public function createProject(): void
    {
        $this->run(function () {
            app(CreateProjectFromQuotation::class)->handle(auth()->user(), $this->quotation, $this->version);
            $this->redirectRoute('quotations.pd', $this->quotation);
        });
    }

    private function item(int $id): QuotationItem
    {
        return QuotationItem::where('quotation_id', $this->quotation->id)->findOrFail($id);
    }

    /** Runs an action; refusals become a message and typed values stay on screen. */
    /** Runs an action; a refusal becomes the page's problem message. Returns whether it succeeded. */
    private function run(callable $action, bool $reload = false): bool
    {
        $this->problem = null;
        try {
            $action();
            if ($reload) {
                $this->load();
            }

            return true;
        } catch (ModelNotFoundException) {
            $this->problem = 'This item was removed by someone else — reload to see the latest.';
        } catch (StaleQuotation|QuotationLocked|InvalidQuotationTransition|QuotationIncomplete|DomainException|InvalidArgumentException $e) {
            $this->problem = $e->getMessage();
        }

        return false;
    }

    public function render()
    {
        $q = $this->quotation->fresh(['items', 'preparer', 'revisionOf', 'project']);
        $canUpdate = Gate::allows('update', $q);

        return view('livewire.quotation-page', [
            'q' => $q,
            'totals' => $q->totals(),
            'costing' => $q->costing(),
            'target' => Percent::format(CostingCalculator::COMPANY_TARGET_MARGIN_BP, 0),
            'editable' => $canUpdate && $q->isDraft(),
            'canUpdate' => $canUpdate,
            'canBackToDraft' => Gate::allows('backToDraft', $q) && in_array($q->status->value, ['sent', 'rejected', 'accepted'], true) && ! $q->project,
            'people' => User::where('is_active', true)->orWhere('id', $q->prepared_by)->orderBy('name')->get(['id', 'name']),
            'activity' => $this->tab === 'history' ? $q->activity()->with('user')->get() : collect(),
        ])->title($q->number);
    }
}
