<?php

namespace App\Livewire;

use App\Actions\Quotations\{AddQuotationItem, CreateProjectFromQuotation, DuplicateQuotation, MarkQuotationAccepted, MarkQuotationRejected, MarkQuotationSent, MoveQuotationBackToDraft, MoveQuotationItem, RemoveQuotationItem, ReviseQuotation, UpdateQuotation, UpdateQuotationItem};
use App\Exceptions\{InvalidQuotationTransition, QuotationIncomplete, QuotationLocked, StaleQuotation};
use App\Models\{CompanyProfile, Quotation, QuotationItem, User};
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
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
            'terms' => (string) $q->terms,
        ];
        $this->items = [];
        foreach ($q->items as $i) {
            $this->items["i{$i->id}"] = [
                'title' => $i->title, 'details' => (string) $i->details, 'quantity' => (string) $i->quantity,
                'unit' => $i->unit, 'unit_price' => Money::toInput($i->unit_price_sen),
            ];
        }
    }

    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        if ($parts[0] === 'form') {
            $this->saveForm();
        } elseif ($parts[0] === 'items' && isset($parts[1])) {
            $this->saveItem((int) substr($parts[1], 1));
        }
    }

    private function saveForm(): void
    {
        $this->validate([
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
            'form.terms' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'form.quote_date' => 'date', 'form.validity_days' => 'validity', 'form.attention_email' => 'attention email',
            'form.prepared_by' => 'prepared by', 'form.preparer_email' => 'email', 'form.sst' => 'SST',
        ]);
        $f = $this->form;
        $blank = fn ($v) => trim((string) $v) === '' ? null : trim((string) $v);
        $this->run(fn () => $this->version = app(UpdateQuotation::class)->handle(auth()->user(), $this->quotation, $this->version, [
            'quote_date' => $f['quote_date'],
            'validity_days' => (int) $f['validity_days'],
            'customer_name' => $blank($f['customer_name']),
            'attention' => $blank($f['attention']),
            'attention_phone' => $blank($f['attention_phone']),
            'attention_email' => $blank($f['attention_email']),
            'customer_address' => $blank($f['customer_address']),
            'subject' => $blank($f['subject']),
            'prepared_by' => (int) $f['prepared_by'],
            'preparer_position' => $blank($f['preparer_position']),
            'preparer_phone' => $blank($f['preparer_phone']),
            'preparer_email' => $blank($f['preparer_email']),
            'show_signature' => (bool) $f['show_signature'],
            'show_stamp' => (bool) $f['show_stamp'],
            'sst_bp' => Percent::parseBp($f['sst']),
            'terms' => $f['terms'],
        ])->version);
    }

    private function saveItem(int $id): void
    {
        $k = "i{$id}";
        $this->validate([
            "items.$k.title" => ['required', 'string', 'max:255'],
            "items.$k.details" => ['nullable', 'string', 'max:2000'],
            "items.$k.quantity" => ['required', 'integer', 'between:1,1000000'],
            "items.$k.unit" => ['required', 'string', 'max:50'],
            "items.$k.unit_price" => ['required', new MoneyAmount],
        ], [], ["items.$k.title" => 'title', "items.$k.quantity" => 'quantity', "items.$k.unit" => 'unit', "items.$k.unit_price" => 'unit price']);
        $row = $this->items[$k];
        $this->run(fn () => $this->version = app(UpdateQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version, [
            'title' => $row['title'], 'details' => $row['details'], 'quantity' => (int) $row['quantity'],
            'unit' => $row['unit'], 'unit_price_sen' => Money::parse($row['unit_price']) ?? 0,
        ])->version);
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
        $this->saveForm();
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
    private function run(callable $action, bool $reload = false): void
    {
        $this->problem = null;
        try {
            $action();
            if ($reload) {
                $this->load();
            }
        } catch (ModelNotFoundException) {
            $this->problem = 'This item was removed by someone else — reload to see the latest.';
        } catch (StaleQuotation|QuotationLocked|InvalidQuotationTransition|QuotationIncomplete|DomainException|InvalidArgumentException $e) {
            $this->problem = $e->getMessage();
        }
    }

    public function render()
    {
        $q = $this->quotation->fresh(['items', 'preparer', 'revisionOf', 'project']);
        $canUpdate = Gate::allows('update', $q);

        return view('livewire.quotation-page', [
            'q' => $q,
            'totals' => $q->totals(),
            'editable' => $canUpdate && $q->isDraft(),
            'canUpdate' => $canUpdate,
            'canBackToDraft' => Gate::allows('backToDraft', $q) && in_array($q->status->value, ['sent', 'rejected', 'accepted'], true) && ! $q->project,
            'people' => User::where('is_active', true)->orWhere('id', $q->prepared_by)->orderBy('name')->get(['id', 'name']),
            'activity' => $this->tab === 'history' ? $q->activity()->with('user')->get() : collect(),
        ])->title($q->number);
    }
}
