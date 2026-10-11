<?php

namespace App\Http\Requests;

use App\Models\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;

/**
 * Filtros y parametros de paginacion para GET /api/movements (HU-08 §10).
 */
class ListMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_id' => ['nullable', 'uuid'],
            'type' => ['nullable', 'string', 'max:30', ValidationRule::in(MovementType::pluck('name')->all())],
            'performed_by' => ['nullable', 'uuid'],
            'from_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_id.uuid' => 'El filtro de existencia debe ser un identificador valido.',
            'type.in' => 'El tipo de movimiento no es valido.',
            'performed_by.uuid' => 'El filtro de usuario debe ser un identificador valido.',
            'from_date.date_format' => 'La fecha de inicio debe tener formato YYYY-MM-DD.',
            'to_date.date_format' => 'La fecha de fin debe tener formato YYYY-MM-DD.',
            'to_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'per_page.max' => 'No se pueden solicitar mas de 100 registros por pagina.',
        ];
    }
}
