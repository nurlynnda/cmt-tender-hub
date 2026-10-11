<?php

namespace App\Livewire;

use App\Actions\Tenders\{AddDocument, AddDocuments, AddSubtask, CancelTender, DropTender, MarkTenderAwarded, MarkTenderDone, MarkTenderLost, RemoveDocument, ReopenTender, ToggleDocument, UpdateTender};
use App\Enums\{TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Exceptions\{CostingRequired, DocumentsIncomplete, InvalidTenderTransition, StaleTenderException};
use App\Livewire\Concerns\Toasts;
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
    use Toasts;

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
    public string $dropReason = '';
    public string $newDocument = '';
    /** Text typed into each task's "Add subtask" box, keyed by the task's id. */
    public array $newSubtasks = [];
    public string $bulkDocuments = '';
    /** A short confirmation shown on the Documents tab, e.g. "Added 3 documents". */
    public ?string $notice = null;

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
        if ($this->apply(fn () => app(UpdateTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->form->toData()), 'Tender details saved')) {
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
            $pending = $this->tender->documents()->whereNull('parent_id')->where('is_done', false)->pluck('name')->all();
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
        // Costing edits may have arrived after the dialog opened; never submit an out-of-date price.
        if ($this->costingDirty) {
            $this->costingProblem = 'Save your costing changes first.';
            $this->modal = null;

            return;
        }
        $this->apply(fn () => app(MarkTenderDone::class)->handle(auth()->user(), $this->tender, $this->version), 'Tender marked Done');
    }

    public function cancelTender(): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:1000']]);
        $this->apply(fn () => app(CancelTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->cancelReason), 'Tender cancelled');
    }

    public function markAwarded(): void
    {
        $this->apply(fn () => app(MarkTenderAwarded::class)->handle(auth()->user(), $this->tender, $this->version), 'Tender marked Awarded');
    }

    public function markLost(): void
    {
        $this->validate([
            'winningPrice' => ['nullable', new MoneyAmount],
            'lostReason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->apply(fn () => app(MarkTenderLost::class)->handle(
            auth()->user(), $this->tender, $this->version, Money::parse($this->winningPrice), $this->lostReason,
        ), 'Tender marked Lost');
    }

    public function dropTender(): void
    {
        $this->validate(['dropReason' => ['nullable', 'string', 'max:1000']]);
        $this->apply(fn () => app(DropTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->dropReason), 'Tender dropped');
    }

    public function reopen(): void
    {
        $this->apply(fn () => app(ReopenTender::class)->handle(auth()->user(), $this->tender, $this->version), 'Tender reopened');
    }

    public function toggleDocument(int $documentId): void
    {
        $this->apply(fn () => app(ToggleDocument::class)->handle(auth()->user(), $this->tender, $this->version, $documentId), 'Task updated');
    }

    public function addDocument(): void
    {
        $this->validate(['newDocument' => ['required', 'string', 'max:255']]);
        if ($this->apply(fn () => app(AddDocument::class)->handle(auth()->user(), $this->tender, $this->version, $this->newDocument), 'Document added')) {
            $this->newDocument = '';
        }
    }

    public function addSubtask(int $parentId): void
    {
        $this->validate(["newSubtasks.{$parentId}" => ['required', 'string', 'max:255']], attributes: ["newSubtasks.{$parentId}" => 'subtask']);
        if ($this->apply(fn () => app(AddSubtask::class)->handle(auth()->user(), $this->tender, $this->version, $parentId, $this->newSubtasks[$parentId]), 'Subtask added')) {
            unset($this->newSubtasks[$parentId]);
        }
    }

    public function bulkAddDocuments(): void
    {
        $this->validate(['bulkDocuments' => ['required', 'string', 'max:20000']]);
        $added = 0;
        $ok = $this->apply(function () use (&$added) {
            [$fresh, $added] = app(AddDocuments::class)->handle(auth()->user(), $this->tender, $this->version, $this->bulkDocuments);

            return $fresh;
        }, null);
        if ($ok) {
            $this->bulkDocuments = '';
            $this->notice = $added ? "Added {$added} documents" : 'Nothing new to add';
            $this->toast($this->notice, $added ? 'success' : 'info');
        }
    }

    public function removeDocument(int $documentId): void
    {
        $this->apply(fn () => app(RemoveDocument::class)->handle(auth()->user(), $this->tender, $this->version, $documentId), 'Removed from the checklist');
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
    private function apply(callable $action, ?string $success = null): bool
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
            if ($success) {
                $this->toastNextPage($success);
            }
            $this->redirectRoute('tenders.show', $fresh);
        } elseif ($success) {
            $this->toast($success);
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
        $topLevel = $documents->whereNull('parent_id');

        return view('livewire.tender-detail', [
            'documents' => $documents,
            'topLevelCount' => $topLevel->count(),
            'doneCount' => $topLevel->where('is_done', true)->count(),
            'costing' => $this->modal === 'done' ? $this->tender->costingSummary() : null,
            'hasPd' => $this->tender->status === TenderStatus::Awarded && $this->tender->project()->exists(),
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
