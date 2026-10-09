<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clase que recibe los datos y los valida antes de que lleguen al controlador.
 */
class StoreProductRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'cum' => [
                'required',
                'string',
                'max:30',
                Rule::unique('products', 'cum')
                    ->ignore($this->route('product'))
                    ->whereNull('deleted_at')
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('products', 'barcode')
                    ->ignore($this->route('product'))
                    ->whereNull('deleted_at')
            ],
            'invima_registration' => [
                'required',
                'string',
                'max:60',
                Rule::unique('products', 'invima_registration')
                    ->ignore($this->route('product'))
                    ->whereNull('deleted_at')
            ],
            'unit_id' => 'required|uuid|exists:units,id',
            'group_id' => 'required|uuid|exists:groups,id',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ];
    }

    // Preparar los datos antes de la validación
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim($this->name),
            'cum' => trim($this->cum),
            'barcode' => !empty($this->barcode) ? trim($this->barcode) : null,
            'invima_registration' => trim($this->invima_registration),
            'description' => $this->description ? trim($this->description) : null,
            'status' => (bool) $this->status,
        ]);
    }

    // Mensajes de error personalizados
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.max' => 'El nombre debe tener como máximo 100 caracteres.',
            'cum.required' => 'El CUM es requerido.',
            'cum.max' => 'El CUM debe tener como máximo 30 caracteres.',
            'barcode.max' => 'El código de barras debe tener como máximo 60 caracteres.',
            'invima_registration.required' => 'El registro de Invima es requerido.',
            'invima_registration.max' => 'El registro de Invima debe tener como máximo 60 caracteres.',
            'unit_id.required' => 'La unidad es requerida.',
            'unit_id.uuid' => 'La unidad debe ser un UUID válido.',
            'unit_id.exists' => 'La unidad seleccionada no existe.',
            'group_id.required' => 'El grupo es requerido.',
            'group_id.uuid' => 'El grupo debe ser un UUID válido.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
            'description.string' => 'La descripción debe ser texto.',
            'status.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
