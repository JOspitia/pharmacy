<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clase que recibe los datos y los valida antes de que lleguen al controlador.
 */
class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retorna un array con las reglas de validación.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\(\)°]+$/u',
                Rule::unique('groups', 'name')
                    ->ignore($this->route('group')?->id)
                    ->whereNull('deleted_at')
            ],
            'description' => 'nullable|string|max:300',
        ];
    }

    /**
     * Retorna un array con los mensajes de error.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El campo nombre es obligatorio.',
            'name.string' => 'El campo nombre debe ser una cadena de texto.',
            'name.max' => 'El campo nombre debe tener como máximo 60 caracteres.',
            'name.unique' => 'El nombre ya existe en la base de datos.',
            'name.regex' => 'El nombre solo permite letras, acentos, ñ, dígitos, espacios, guiones, paréntesis y el símbolo de grado.',
            'description.string' => 'El campo descripción debe ser una cadena de texto.',
            'description.max' => 'El campo descripción debe tener como máximo 300 caracteres.',
        ];
    }

    /**
     * Retorna un array con los atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
        ];
    }
}