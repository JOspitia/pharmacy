<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros y parametros de paginacion para GET /api/inventories (HU-08 §10).
 */
class ListInventoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
            'lot_id' => ['nullable', 'uuid'],
            'expiring_within' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:expiration_date,quantity,product_name'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.uuid' => 'El filtro de bodega debe ser un identificador valido.',
            'product_id.uuid' => 'El filtro de producto debe ser un identificador valido.',
            'lot_id.uuid' => 'El filtro de lote debe ser un identificador valido.',
            'expiring_within.integer' => 'El filtro de vencimiento debe ser un numero entero.',
            'expiring_within.min' => 'El filtro de vencimiento debe ser al menos 1 dia.',
            'sort.in' => 'La columna de ordenamiento no es valida.',
            'order.in' => 'La direccion de ordenamiento debe ser asc o desc.',
            'per_page.max' => 'No se pueden solicitar mas de 100 registros por pagina.',
        ];
    }
}
