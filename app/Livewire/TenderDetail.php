<?php

namespace App\Livewire;

use App\Actions\Tenders\{AddDocument, CancelTender, MarkTenderAwarded, MarkTenderDone, MarkTenderLost, RemoveDocument, ReopenTender, ToggleDocument, UpdateTender};
use App\Enums\{TenderCategory, TenderMode, TenderType};
use App\Exceptions\{CostingRequired, DocumentsIncomplete, InvalidTenderTransition, StaleTenderException};
use App\Livewire\Forms\TenderForm;
use App\Models\{Tender, User};
use App\Rules\MoneyAmount;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\{Layout, On, Url};
use Livewire\Component;

#[Layout('layouts.app')]
class TenderDetail extends Component
{
    public Tender $tender;
    public int $version;
    #[Url] public string $tab = 'overview';

    public TenderForm $form;
    public bool $editing = false;

    public ?string $modal = null;
    public ?string $conflict = null;
    public array $pendingDocuments = [];

    public ?string $costingProblem = null;
    public string $cancelReason = '';
    public string $winningPrice = '';
    public string $lostReason = '';
    public string $newDocument = '';

    /** Set by the Costing tab while it holds unsaved edits. */
    public bool $costingDirty = false;

    public function mount(Tender $tender): void
    {
        $this->tender = $tender;
        $this->version = $tender->version;
    }

    public function startEdit(): void
    {
        $this->authorize('update', $this->tender);
        $this->form->fillFrom($this->tender);
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    public function save(): void
    {
        $this->authorize('update', $this->tender);
        $this->form->keepPeople([$this->tender->pic_id, $this->tender->owner_id]);
        $this->form->validate();
        if ($this->apply(fn () => app(UpdateTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->form->toData()))) {
            $this->editing = false;
        }
    }

    public function openModal(string $name): void
    {
        $ability = $name === 'reopen' ? 'reopen' : 'update';
        $this->authorize($ability, $this->tender);
        $this->resetValidation();
        $this->pendingDocuments = [];
        $this->costingProblem = null;

        if ($name === 'done') {
            $pending = $this->tender->documents()->where('is_done', false)->pluck('name')->all();
            if ($pending !== []) {
                $this->pendingDocuments = $pending;

                return;
            }
            if ($this->costingDirty) {
                $this->costingProblem = 'Save your costing changes first.';

                return;
            }
            if (! $this->tender->fresh()->hasCosting()) {
                $this->costingProblem = (new CostingRequired)->getMessage();

                return;
            }
        }

        $this->modal = $name;
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    public function markDone(): void
    {
        $this->apply(fn () => app(MarkTenderDone::class)->handle(auth()->user(), $this->tender, $this->version));
    }

    public function cancelTender(): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:1000']]);
        $this->apply(fn () => app(CancelTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->cancelReason));
    }

    public function markAwarded(): void
    {
        $this->apply(fn () => app(MarkTenderAwarded::class)->handle(auth()->user(), $this->tender, $this->version));
    }

    public function markLost(): void
    {
        $this->validate([
            'winningPrice' => ['nullable', new MoneyAmount],
            'lostReason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->apply(fn () => app(MarkTenderLost::class)->handle(
            auth()->user(), $this->tender, $this->version, Money::parse($this->winningPrice), $this->lostReason,
        ));
    }

    public function reopen(): void
    {
        $this->apply(fn () => app(ReopenTender::class)->handle(auth()->user(), $this->tender, $this->version));
    }

    public function toggleDocument(int $documentId): void
    {
        $this->apply(fn () => app(ToggleDocument::class)->handle(auth()->user(), $this->tender, $this->version, $documentId));
    }

    public function addDocument(): void
    {
        $this->validate(['newDocument' => ['required', 'string', 'max:255']]);
        if ($this->apply(fn () => app(AddDocument::class)->handle(auth()->user(), $this->tender, $this->version, $this->newDocument))) {
            $this->newDocument = '';
        }
    }

    public function removeDocument(int $documentId): void
    {
        $this->apply(fn () => app(RemoveDocument::class)->handle(auth()->user(), $this->tender, $this->version, $documentId));
    }

    #[On('costing-dirty')]
    public function costingDirty(bool $dirty): void
    {
        $this->costingDirty = $dirty;
    }

    #[On('costing-saved')]
    public function costingSaved(int $version): void
    {
        $this->version = $version;
        $this->costingDirty = false;
        $this->tender = $this->tender->fresh();
    }

    /** Runs an action; on success refreshes local state. Returns false when the change was refused. */
    private function apply(callable $action): bool
    {
        try {
            $fresh = $action();
        } catch (StaleTenderException|InvalidTenderTransition $e) {
            $this->conflict = $e->getMessage();
            $this->modal = null;

            return false;
        } catch (DocumentsIncomplete $e) {
            $this->pendingDocuments = $e->pending;
            $this->modal = null;

            return false;
        } catch (CostingRequired $e) {
            $this->costingProblem = $e->getMessage();
            $this->modal = null;

            return false;
        }

        if ($fresh->status !== $this->tender->status) {
            // Full reload so the sidebar counts (rendered outside this component) stay accurate.
            $this->redirectRoute('tenders.show', $fresh);
        }

        $this->tender = $fresh;
        $this->version = $fresh->version;
        $this->conflict = null;
        $this->modal = null;
        $this->pendingDocuments = [];
        $this->costingProblem = null;
        $this->reset('cancelReason', 'winningPrice', 'lostReason');

        return true;
    }

    public function render()
    {
        $documents = $this->tender->documents()->with('doneBy')->get();

        return view('livewire.tender-detail', [
            'documents' => $documents,
            'doneCount' => $documents->where('is_done', true)->count(),
            'costing' => $this->modal === 'done' ? $this->tender->costingSummary() : null,
            'activity' => $this->tender->activity()->with('user')->get(),
            'canEdit' => Gate::allows('update', $this->tender),
            'canReopen' => Gate::allows('reopen', $this->tender),
            'people' => User::where('is_active', true)
                ->orWhereIn('id', array_filter([$this->tender->pic_id, $this->tender->owner_id]))
                ->orderBy('name')->get(['id', 'name', 'is_active']),
            'ministries' => config('tenderhub.ministries'),
            'modes' => TenderMode::cases(),
            'types' => TenderType::cases(),
            'categories' => TenderCategory::cases(),
        ])->title($this->tender->wo_number);
    }
}
