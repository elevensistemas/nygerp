<?php

namespace App\Providers;

use App\Models\DriverImportRun;
use App\Models\DriverLiquidationSetting;
use App\Models\PlanillaPagoChofer;
use App\Models\ReciboChofer;
use App\Policies\DriverImportRunPolicy;
use App\Policies\DriverLiquidationSettingPolicy;
use App\Policies\PlanillaPagoChoferPolicy;
use App\Policies\ReciboChoferPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ReciboChofer::class => ReciboChoferPolicy::class,
        PlanillaPagoChofer::class => PlanillaPagoChoferPolicy::class,
        DriverLiquidationSetting::class => DriverLiquidationSettingPolicy::class,
        DriverImportRun::class => DriverImportRunPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
