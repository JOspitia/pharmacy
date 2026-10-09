<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clase que valida los query params del endpoint de reporte de inventario de productos
 */
class ReportRequest extends FormRequest
{

    // Validación de autorización
    public function authorize(): bool
    {
        return true;
    }

    // Reglas de validación
    public function rules(): array
    {
        return [
            'cum'       => 'nullable|string|max:60',
            'name'      => 'nullable|string|max:120',
            'bodega_id' => 'nullable|uuid|exists:bodegas,id',
            'group_id'  => 'nullable|uuid|exists:groups,id',
            'lot_code'  => 'nullable|string|max:60',
            'estado'    => 'nullable|in:con_stock,por_vencer,vencido,todos',
            'date_from' => 'nullable|date|before_or_equal:date_to',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'sort_by'   => 'nullable|in:products.cum,products.name,products.barcode,products.invima_registration,groups.name,units.name,lots.name,bodegas.name,lots.stock,lots.expiration_date',
            'sort_dir'  => 'nullable|in:asc,desc',
            'per_page'  => 'nullable|integer|min:1|max:100',
            'page'      => 'nullable|integer|min:1',
        ];
    }

    // Mensajes de error
    public function messages(): array
    {
        return [
            'cum.max'           => 'El CUM no puede tener más de 60 caracteres.',
            'name.max'          => 'El nombre no puede tener más de 120 caracteres.',
            'bodega_id.uuid'    => 'El ID de bodega debe ser un UUID válido.',
            'bodega_id.exists'  => 'La bodega seleccionada no existe.',
            'group_id.uuid'     => 'El ID de grupo debe ser un UUID válido.',
            'group_id.exists'   => 'El grupo seleccionado no existe.',
            'lot_code.max'      => 'El código de lote no puede tener más de 60 caracteres.',
            'estado.in'         => 'El estado debe ser: con_stock, por_vencer, vencido o todos.',
            'date_from.date'    => 'La fecha desde debe ser una fecha válida.',
            'date_from.before_or_equal' => 'La fecha desde debe ser anterior o igual a la fecha hasta.',
            'date_to.date'      => 'La fecha hasta debe ser una fecha válida.',
            'date_to.after_or_equal'   => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'sort_by.in'        => 'La columna de ordenamiento no es válida.',
            'sort_dir.in'       => 'La dirección de orden debe ser asc o desc.',
            'per_page.integer'  => 'per_page debe ser un número entero.',
            'per_page.min'      => 'per_page debe ser al menos 1.',
            'per_page.max'      => 'per_page no puede ser mayor a 100.',
            'page.integer'      => 'page debe ser un número entero.',
            'page.min'          => 'page debe ser al menos 1.',
        ];
    }
}
