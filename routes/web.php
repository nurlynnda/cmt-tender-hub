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
    // Placeholders until Tasks 12 and 14 build these screens:
    Route::get('/tenders/{tender}', \App\Livewire\TenderDetail::class)
        ->whereNumber('tender')->name('tenders.show');
    Route::get('/settings', fn () => 'Settings coming soon')->name('settings');
    Route::get('/settings/users', fn () => 'Manage users coming soon')
        ->middleware('can:manage-users')->name('users.index');
    Route::post('/logout', LogoutController::class)->name('logout');
});
