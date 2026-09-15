<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            \App\Services\DriverPayments\RuleEngine\Contracts\SettlementRuleRepositoryInterface::class,
            \App\Services\DriverPayments\RuleEngine\Repositories\CachedEloquentSettlementRuleRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();
        \App\Models\DriverLogisticsRecord::observe(\App\Observers\DriverLogisticsRecordObserver::class);
    }
}
