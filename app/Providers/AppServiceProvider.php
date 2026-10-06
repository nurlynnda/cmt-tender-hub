<?php

namespace App\Providers;

use App\Enums\Role;
use App\Http\Middleware\EnsureUserIsActive;
use Livewire\Livewire;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\View\Composers\SidebarComposer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // A deactivated account can do nothing, even if a page is still open in their browser.
        Gate::before(fn (User $user) => $user->is_active ? null : false);
        Gate::define('manage-users', fn (User $user) => $user->role === Role::Admin);
        // Livewire's in-page requests skip route middleware unless it is registered here.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);
        View::composer('layouts.partials.sidebar', SidebarComposer::class);
    }
}
