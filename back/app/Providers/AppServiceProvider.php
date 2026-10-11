<?php

namespace App\Providers;

use App\Services\InventoryService;
use App\Services\KardexService;
use App\Services\MovementService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // KardexService es singleton por diseño: RN-03 exige que dos requests
        // concurrentes sobre la misma existencia se serialicen con lockForUpdate.
        // Tener una sola instancia no cambia el comportamiento del lock (que es
        // por fila de la base), pero evita crear una instancia nueva por request.
        $this->app->singleton(KardexService::class);

        $this->app->singleton(InventoryService::class);
        $this->app->singleton(MovementService::class);
    }

    public function boot(): void
    {
        //
    }
}
