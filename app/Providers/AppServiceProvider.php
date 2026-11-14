<?php

namespace App\Providers;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Observers\BoksArsipObserver;
use App\Observers\DokumenArsipObserver;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        DokumenArsip::observe(DokumenArsipObserver::class);
        BoksArsip::observe(BoksArsipObserver::class);
    }
}
