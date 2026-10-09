<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clase que recibe los datos y los valida antes de que lleguen al controlador.
 * Relación 1:N: cada sensor pertenece a una sola bodega (warehouse_id).
 */
class StoreSensorRequest extends FormRequest
{
    // Verificar si el usuario tiene permiso de crear sensores
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
                Rule::unique('sensors', 'name')->whereNull('deleted_at'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:300',
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
            'warehouse_id' => [
                'required',
                'uuid',
                Rule::exists('bodegas', 'id')->where(function ($query) {
                    $query->where('status', true)->whereNull('deleted_at');
                }),
            ],
        ];
    }

    // Preparar los datos antes de la validación
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }

    // Mensajes de error personalizados
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del sensor es requerido.',
            'name.max' => 'El nombre no puede tener más de 60 caracteres.',
            'name.regex' => 'El nombre solo permite letras, acentos, ñ, dígitos, espacios, guiones, paréntesis y el símbolo de grado.',
            'name.unique' => 'El nombre del sensor ya existe.',
            'description.max' => 'La descripción no puede tener más de 300 caracteres.',
            'status.boolean' => 'El estado debe ser verdadero o falso.',
            'warehouse_id.required' => 'La bodega es requerida.',
            'warehouse_id.uuid' => 'La bodega debe ser un UUID válido.',
            'warehouse_id.exists' => 'La bodega seleccionada no existe o está inactiva.',
        ];
    }
}
