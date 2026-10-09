<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define la programación de tareas de la aplicación.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Ejecutar el Comando: readings:generate cada minuto
        $schedule->command('readings:generate')->everyMinute();
    }

    /**
     * Registrar comandos de la aplicación.
     *
     * @return void
     */
    protected function commands(): void
    {   
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
