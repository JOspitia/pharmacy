<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clase que recibe los datos y los valida antes de que lleguen al controlador
 */
class LoginRequest extends FormRequest
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
            'name' => 'required|string',
            'password' => 'required|string',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'name.required'    => 'El campo nombre es obligatorio.',
            'name.string'       => 'El campo nombre debe ser una cadena de texto.',
            'password.required' => 'El campo contraseña es obligatorio.',
            'password.string'   => 'El campo contraseña debe ser una cadena de texto.',
        ];
    }

    /**
     * Atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'password' => 'contraseña',
        ];
    }
}
