<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validaciones de ESTRUCTURA para aprobar un traslado (HU-09, RN-07).
 *
 * El body trae, por cada item, la cantidad aprobada. La cantidad
 * aprobada puede ser MENOR que la solicitada (el regente puede
 * ajustar si el stock bajo desde la solicitud), pero no puede
 * superar la solicitada ni ser negativa.
 *
 * Las validaciones de negocio (estado del traslado, segregacion
 * RN-08, RN-01 lote no vencido, persistencia de approved_by y
 * status) viven en TransferService::approve() dentro de
 * DB::transaction.
 */
class ApproveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El middleware de permisos (module:transfers) ya se encargo del 403.
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.transfer_item_id' => [
                'required',
                'uuid',
                Rule::exists('transfer_items', 'id'),
            ],

            'items.*.approved_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debe especificar al menos un item para aprobar.',
            'items.array' => 'Los items deben ser un arreglo.',
            'items.min' => 'Debe especificar al menos un item para aprobar.',

            'items.*.transfer_item_id.required' => 'Cada item debe tener un identificador.',
            'items.*.transfer_item_id.uuid' => 'El identificador del item debe ser un UUID valido.',
            'items.*.transfer_item_id.exists' => 'Uno de los items seleccionados no existe.',

            'items.*.approved_quantity.required' => 'La cantidad aprobada es obligatoria.',
            'items.*.approved_quantity.integer' => 'La cantidad aprobada debe ser un numero entero.',
            'items.*.approved_quantity.min' => 'La cantidad aprobada no puede ser negativa.',
        ];
    }
}
