<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**¨
 * Clase que recibe los datos y los valida antes de que lleguen al controlador.
 */
class StoreLotRequest extends FormRequest
{
    // Verificar si el usuario tiene permiso de crear lotes
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
                'max:120',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\(\)°]+$/u',
                Rule::unique('lots', 'name')
                    ->ignore($this->route('lot')?->id)
                    ->whereNull('deleted_at')
            ],
            'product_id' => [
                'required',
                'uuid',
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                    $query->where('status', true);
                }),
            ],
            'warehouse_id' => [
                'required',
                'uuid',
                Rule::exists('bodegas', 'id')->where(function ($query) {
                    $query->where('status', true);
            }),
        ],
            'stock' => 'required|integer|min:0',
            'expiration_date' => [
                'required',
                'date',
                // HU-05 exige fecha ESTRICTAMENTE futura: "anterior o igual a la
                // fecha actual" se rechaza (SP-13, CL-08, CA-25).
                // Al editar solo se exige si el usuario cambia la fecha, porque un
                // lote ya vencido es un estado valido del dominio
                // (derived_status = "Vencido") y debe seguir siendo editable.
                Rule::when(
                    $this->isMethod('POST')
                    || $this->expiration_date !== $this->route('lot')?->expiration_date?->format('Y-m-d'),
                    ['after:today']
                ),
            ],
            'description' => 'nullable|string|max:300',
        ];
    }

    // Preparar los datos antes de la validación
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim($this->name)
        ]);
    }

    // Mensajes de error personalizados
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.max' => 'El nombre debe tener como máximo 120 caracteres.',
            'name.regex' => 'El nombre solo puede contener letras, números, espacios, guiones, paréntesis y el símbolo de grado.',
            'name.unique' => 'El nombre ya existe.',
            'product_id.required' => 'El producto es requerido.',
            'product_id.uuid' => 'El producto debe ser un UUID válido.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'warehouse_id.required' => 'La bodega es requerida.',
            'warehouse_id.uuid' => 'La bodega debe ser un UUID válido.',
            'warehouse_id.exists' => 'La bodega seleccionada no existe.',
            'stock.required' => 'El stock es requerido.',
            'stock.numeric' => 'El stock debe ser un número.',
            'stock.min' => 'El stock debe ser mayor o igual a 0.',
            'expiration_date.required' => 'La fecha de expiración es requerida.',
            'expiration_date.date' => 'La fecha de expiración debe ser una fecha válida.',
            'expiration_date.after' => 'La fecha de vencimiento debe ser posterior a la fecha actual.',
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción debe tener como máximo 300 caracteres.',
        ];
    }
}
