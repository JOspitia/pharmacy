<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clase que recibe los datos y los valida antes de que lleguen al controlador.
 */
class StoreBodegaRequest extends FormRequest
{
    // Verificar si el usuario tiene permiso de crear bodegas
    public function authorize(): bool
    {
        return true;
    }

    // Reglas de validación
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\(\)°]+$/u',
                Rule::unique('bodegas', 'name')
                    ->ignore($this->route('bodega')?->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:300',
            ],
        ];
    }

    // Mensajes de error
    public function messages(): array
    {
        return [
            'name.required' => 'El campo nombre es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 60 caracteres.',
            'name.regex' => 'El nombre solo permite letras, acentos, ñ, dígitos, espacios, guiones, paréntesis y el símbolo de grado.',
            'name.unique' => 'El nombre de bodega ya existe.',
            'description.max' => 'La descripción no puede tener más de 300 caracteres.',
        ];
    }
}
