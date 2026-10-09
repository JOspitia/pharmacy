<?php

namespace App\Events;

use App\Models\Reading;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando se registra una nueva lectura de temperatura y humedad.
 */
class ReadingRecorded
{
    // Atributos para el evento
    
    /**
     * Dispatchable — para poder hacer ReadingRecorded::dispatch($reading)
     * SerializesModels — para que el $reading se serialize correctamente si se decide poner el listener en cola en el futuro
     * InteractsWithSockets — solo si vas a hacer broadcastOn() / broadcasting
     */
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Propiedad del evento
    public $reading;

    /**
     * Crear una nueva instancia del evento.
     */
    public function __construct(Reading $reading)
    {
        // Guardar la lectura en la propiedad $reading
        $this->reading = $reading;
    }
}