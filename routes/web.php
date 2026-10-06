<?php

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\{ForgotPassword, Login, ResetPassword};
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/tenders/in-progress');
    Route::get('/tenders/{list}', \App\Livewire\TenderList::class)
        ->whereIn('list', ['in-progress', 'done', 'awarded', 'lost'])
        ->name('tenders.index');
    Route::get('/tenders/{tender}', \App\Livewire\TenderDetail::class)
        ->whereNumber('tender')->name('tenders.show');
    Route::get('/find-tenders', \App\Livewire\FindTenders::class)->name('find-tenders.index');
    Route::get('/find-tenders/{collectedTender}', \App\Livewire\CollectedTenderDetail::class)
        ->whereNumber('collectedTender')->name('find-tenders.show');
    Route::get('/settings', \App\Livewire\Settings::class)->name('settings');
    Route::get('/settings/users', \App\Livewire\ManageUsers::class)
        ->middleware('can:manage-users')->name('users.index');
    Route::get('/settings/finance', \App\Livewire\FinanceSettings::class)
        ->middleware('can:manage-finance')->name('finance.settings');
    Route::post('/logout', LogoutController::class)->name('logout');
});
