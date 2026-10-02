<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\ApprovalRequest::observe(\App\Observers\ApprovalRequestObserver::class);
        \App\Models\Memo::observe(\App\Observers\MemoObserver::class);
    }
}
