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
    // TEMPORARY placeholder — replaced by the TenderList screen in Task 10.
    Route::get('/tenders/{list}', fn (string $list) => 'Tender list coming soon')
        ->whereIn('list', ['in-progress', 'done', 'awarded', 'lost'])
        ->name('tenders.index');
    Route::post('/logout', LogoutController::class)->name('logout');
});
