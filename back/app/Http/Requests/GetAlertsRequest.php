<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clase que recibe los datos y los valida antes de que lleguen al controlador
 */
class GetAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'date_from.date'      => 'La fecha inicial debe ser una fecha válida.',
            'date_to.date'        => 'La fecha final debe ser una fecha válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser mayor o igual a la fecha inicial.',
        ];
    }

    /**
     * Atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'fecha inicial',
            'date_to'   => 'fecha final',
        ];
    }
}
