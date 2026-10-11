<?php

use App\Actions\Tenders\{AddDocument, RemoveDocument, ToggleDocument};
use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, TenderDocument, User};
use Illuminate\Database\Eloquent\ModelNotFoundException;

function docTender(TenderStatus $status = TenderStatus::InProgress): array
{
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'status' => $status]);
    $doc = TenderDocument::factory()->for($tender)->create(['name' => 'Pricing Schedule', 'position' => 1]);

    return [$pic, $tender, $doc];
}

it('ticks and unticks a document, recording who and when', function () {
    [$pic, $tender, $doc] = docTender();

    $t = app(ToggleDocument::class)->handle($pic, $tender, 1, $doc->id);
    expect($doc->fresh()->is_done)->toBeTrue()
        ->and($doc->fresh()->done_by)->toBe($pic->id)
        ->and($t->version)->toBe(2)
        ->and($t->activity->first()->description)->toBe('Ticked: Pricing Schedule');

    $t = app(ToggleDocument::class)->handle($pic, $t, 2, $doc->id);
    expect($doc->fresh()->is_done)->toBeFalse()
        ->and($doc->fresh()->done_by)->toBeNull()
        ->and($t->activity->first()->description)->toBe('Unticked: Pricing Schedule');
});

it('adds a document at the end of the list', function () {
    [$pic, $tender] = docTender();

    $t = app(AddDocument::class)->handle($pic, $tender, 1, '  Surat Akuan  ');

    expect($t->documents->pluck('name')->all())->toBe(['Pricing Schedule', 'Surat Akuan'])
        ->and($t->documents->last()->position)->toBe(2)
        ->and($t->activity->first()->description)->toBe('Added document: Surat Akuan');
});

it('refuses a blank document name', function () {
    [$pic, $tender] = docTender();

    app(AddDocument::class)->handle($pic, $tender, 1, '  ');
})->throws(InvalidArgumentException::class);

it('removes a document', function () {
    [$pic, $tender, $doc] = docTender();

    $t = app(RemoveDocument::class)->handle($pic, $tender, 1, $doc->id);

    expect($t->documents)->toHaveCount(0)
        ->and($t->activity->first()->description)->toBe('Removed document: Pricing Schedule');
});

it('cannot touch a document belonging to another tender', function () {
    [$pic, $tender] = docTender();
    [, , $foreign] = docTender();

    app(ToggleDocument::class)->handle($pic, $tender, 1, $foreign->id);
})->throws(ModelNotFoundException::class);

it('locks the checklist once the tender is no longer in progress', function () {
    [$pic, $tender, $doc] = docTender(TenderStatus::Done);

    app(ToggleDocument::class)->handle($pic, $tender, 1, $doc->id);
})->throws(InvalidTenderTransition::class);

it('refuses checklist changes from an out-of-date page', function () {
    [$pic, $tender, $doc] = docTender();

    app(ToggleDocument::class)->handle($pic, $tender, 5, $doc->id);
})->throws(StaleTenderException::class);

it('adds several documents at once, skipping blanks and names already there', function () {
    $pic = \App\Models\User::factory()->create();
    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);
    $t->documents()->create(['name' => 'Company Profile', 'position' => 1]);

    [$fresh, $added] = app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $t, $t->version,
        "Site Visit Report\n\n  company profile \nInsurance Certificate\nSite visit report\n".str_repeat('x', 300));

    expect($added)->toBe(3)
        ->and($fresh->documents()->pluck('name')->all())->toBe(['Company Profile', 'Site Visit Report', 'Insurance Certificate', str_repeat('x', 255)])
        ->and($fresh->version)->toBe($t->version + 1)
        ->and($fresh->activity()->first()->description)->toStartWith('Added 3 documents: Site Visit Report, Insurance Certificate');
});

it('changes nothing when there is nothing new to add', function () {
    $pic = \App\Models\User::factory()->create();
    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);

    [$fresh, $added] = app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $t, $t->version, "\n  \n");

    expect($added)->toBe(0)->and($fresh->version)->toBe($t->version)->and($fresh->activity()->count())->toBe(0);
});

it('refuses bulk add on a locked tender, for strangers, and on an old version', function () {
    $pic = \App\Models\User::factory()->create();
    $done = \App\Models\Tender::factory()->status(\App\Enums\TenderStatus::Done)->create(['pic_id' => $pic->id]);
    expect(fn () => app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $done, $done->version, 'A'))
        ->toThrow(\App\Exceptions\InvalidTenderTransition::class);

    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);
    expect(fn () => app(\App\Actions\Tenders\AddDocuments::class)->handle(\App\Models\User::factory()->create(), $t, $t->version, 'A'))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class)
        ->and(fn () => app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $t, $t->version + 3, 'A'))
        ->toThrow(\App\Exceptions\StaleTenderException::class);
    expect($t->fresh()->documents()->count())->toBe(0);
});

// ---- Subtasks: a document is a task for the PIC; a task can have subtasks ----

function taskWithSubtasks(): array
{
    [$pic, $tender, $task] = docTender();
    $tender = app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 1, $task->id, 'Get SSM certificate');
    $tender = app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 2, $task->id, 'Get bank letter');

    return [$pic, $tender, $task->fresh()];
}

it('adds a subtask under a task, in order, and logs it', function () {
    [$pic, $tender, $task] = taskWithSubtasks();

    expect($task->children->pluck('name')->all())->toBe(['Get SSM certificate', 'Get bank letter'])
        ->and($task->children->every(fn ($c) => $c->tender_id === $tender->id && $c->parent_id === $task->id))->toBeTrue()
        ->and($tender->version)->toBe(3)
        ->and($tender->activity->first()->description)->toBe('Added subtask: Get bank letter (under Pricing Schedule)');
});

it('does not allow a subtask under a subtask, a blank name, or a task of another tender', function () {
    [$pic, $tender, $task] = taskWithSubtasks();
    $sub = $task->children->first();
    [, , $foreign] = docTender();

    expect(fn () => app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 3, $sub->id, 'Deeper'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 3, $foreign->id, 'Nope'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 3, $task->id, '  '))->toThrow(InvalidArgumentException::class);
});

it('marks a task done only when all of its subtasks are ticked, and open again when one is unticked', function () {
    [$pic, $tender, $task] = taskWithSubtasks();
    [$a, $b] = $task->children;

    $tender = app(ToggleDocument::class)->handle($pic, $tender, 3, $a->id);
    expect($task->fresh()->is_done)->toBeFalse();

    $tender = app(ToggleDocument::class)->handle($pic, $tender, 4, $b->id);
    expect($task->fresh()->is_done)->toBeTrue()->and($task->fresh()->done_by)->toBe($pic->id);

    app(ToggleDocument::class)->handle($pic, $tender, 5, $a->id);
    expect($task->fresh()->is_done)->toBeFalse()->and($task->fresh()->done_by)->toBeNull();
});

it('ticks and unticks every subtask when the task itself is ticked or unticked', function () {
    [$pic, $tender, $task] = taskWithSubtasks();

    $tender = app(ToggleDocument::class)->handle($pic, $tender, 3, $task->id);
    expect($task->fresh()->is_done)->toBeTrue()->and($task->children()->where('is_done', true)->count())->toBe(2);

    app(ToggleDocument::class)->handle($pic, $tender, 4, $task->id);
    expect($task->fresh()->is_done)->toBeFalse()->and($task->children()->where('is_done', true)->count())->toBe(0);
});

it('reopens a finished task when a new subtask is added to it', function () {
    [$pic, $tender, $task] = taskWithSubtasks();
    $tender = app(ToggleDocument::class)->handle($pic, $tender, 3, $task->id);

    app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 4, $task->id, 'One more thing');

    expect($task->fresh()->is_done)->toBeFalse();
});

it('finishes a task when its last unticked subtask is removed, and removes subtasks with their task', function () {
    [$pic, $tender, $task] = taskWithSubtasks();
    [$a, $b] = $task->children;
    $tender = app(ToggleDocument::class)->handle($pic, $tender, 3, $a->id);

    $tender = app(RemoveDocument::class)->handle($pic, $tender, 4, $b->id);
    expect($task->fresh()->is_done)->toBeTrue();

    app(RemoveDocument::class)->handle($pic, $tender, 5, $task->id);
    expect(TenderDocument::count())->toBe(0);
});

it('counts only top-level tasks in the progress figures and in Mark Done', function () {
    [$pic, $tender, $task] = taskWithSubtasks();

    $row = Tender::withDocumentCounts()->find($tender->id);
    expect($row->documents_count)->toBe(1)->and($row->documents_done_count)->toBe(0)->and($row->documentPercent())->toBe(0);

    expect(fn () => app(\App\Actions\Tenders\MarkTenderDone::class)->handle($pic, $tender, 3))
        ->toThrow(\App\Exceptions\DocumentsIncomplete::class, '1 document(s) still not ticked: Pricing Schedule');
});

it('locks subtask changes once the tender is no longer in progress', function () {
    [$pic, $tender, $task] = docTender(TenderStatus::Done);

    app(\App\Actions\Tenders\AddSubtask::class)->handle($pic, $tender, 1, $task->id, 'Late');
})->throws(InvalidTenderTransition::class);
