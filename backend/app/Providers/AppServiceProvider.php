<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // Token yechishda User global scope'i chetlab o'tiladi —
        // izohni App\Models\PersonalAccessToken da o'qing.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
