<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        Model::preventLazyLoading(! app()->isProduction());

        Gate::before(function (User $user, string $ability) {
            return app(AuthorizationService::class)
                ->can($user, $ability)
                ? true
                : null;
        });
    }
}
