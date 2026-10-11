<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validaciones de ESTRUCTURA para recibir un traslado (HU-09, RN-07).
 *
 * El body trae, por cada item, la cantidad efectivamente recibida
 * en bodega destino. La cantidad recibida puede ser MENOR que la
 * aprobada (la HU-09 define que un traslado puede ser recibido
 * parcialmente). Si delivered < approved, el item genera una
 * discrepancia y el traslado pasa a RECIBIDO_PARCIAL.
 *
 * Las validaciones de negocio (estado del traslado, comparacion
 * delivered <= approved por item, generacion de discrepancias,
 * Kardex ENTRADA por item) viven en TransferService::receive()
 * dentro de DB::transaction.
 */
class ReceiveTransferRequest extends FormRequest
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

            'items.*.delivered_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debe especificar al menos un item recibido.',
            'items.array' => 'Los items deben ser un arreglo.',
            'items.min' => 'Debe especificar al menos un item recibido.',

            'items.*.transfer_item_id.required' => 'Cada item debe tener un identificador.',
            'items.*.transfer_item_id.uuid' => 'El identificador del item debe ser un UUID valido.',
            'items.*.transfer_item_id.exists' => 'Uno de los items seleccionados no existe.',

            'items.*.delivered_quantity.required' => 'La cantidad recibida es obligatoria.',
            'items.*.delivered_quantity.integer' => 'La cantidad recibida debe ser un numero entero.',
            'items.*.delivered_quantity.min' => 'La cantidad recibida no puede ser negativa.',
        ];
    }
}
