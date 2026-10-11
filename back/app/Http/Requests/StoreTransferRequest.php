<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validaciones de ESTRUCTURA para crear un traslado (HU-09, RN-07).
 *
 * Aca se valida solo el formato y la existencia de IDs referenciados.
 * Las validaciones de negocio (stock disponible, consistencia
 * bodega-inventario, invariantes de la maquina de estados) viven en
 * TransferService dentro de DB::transaction, donde pueden consultar
 * y escribir de forma atomica.
 *
 * Tres capas de validacion:
 *
 *   1. StoreTransferRequest (aca): formato de IDs, bodegas distintas,
 *      items no vacios, existencia y formato de cada item.
 *   2. TransferService::create: stock disponible en origen, consistencia
 *      bodega-inventario, invariantes de la maquina de estados, y la
 *      escritura de los movimientos del Kardex al despachar.
 *   3. Base de datos: CHECK (from_warehouse_id <> to_warehouse_id),
 *      FKs, UNIQUE (transfer_id, inventory_id) en transfer_items.
 *
 * Por que el stock NO se valida aca: el FormRequest se ejecuta antes
 * de la transaccion del service. Entre ese momento y el SELECT FOR
 * UPDATE del service, otro request concurrente puede consumir el stock
 * que se valido aca. Validar en withValidator seria la primera linea
 * de defensa, pero daria una falsa sensacion de seguridad.
 *
 * La HU-09 (§10 Contrato de API) espera del body:
 *   - to_warehouse_id      (uuid, required, exists)
 *   - items[]              (array, min 1)
 *   - items[].inventory_id (uuid, required, exists)
 *   - items[].requested_quantity (int, required, min 1)
 *   - notes                (string, nullable, max 1000)
 *
 * from_warehouse_id no viene en el body: la HU-09 lo deduce del
 * inventory_id de cada item, porque un traslado mueve mercancia
 * desde UNA bodega de origen. La consistencia bodega-origen
 * se valida en el service.
 */
class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El middleware de permisos (module:transfers) ya se encargo del 403.
        return true;
    }

    public function rules(): array
    {
        return [
            'to_warehouse_id' => [
                'required',
                'uuid',
                Rule::exists('bodegas', 'id')->where(fn ($query) => $query->where('status', true)),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.inventory_id' => [
                'required',
                'uuid',
                // Un mismo inventario (producto + lote) no puede repetirse
                // dentro de un traslado: es la misma existencia fisica, asi
                // que dos filas serian redundantes y la segunda violaria el
                // UNIQUE (transfer_id, inventory_id) de transfer_items.
                // Sin esto el error sale como 500 por QueryException en vez
                // de un 422 con mensaje util.
                'distinct',
                Rule::exists('inventories', 'id'),
            ],

            'items.*.requested_quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'to_warehouse_id.required' => 'La bodega de destino es obligatoria.',
            'to_warehouse_id.uuid' => 'La bodega de destino debe ser un identificador valido.',
            'to_warehouse_id.exists' => 'La bodega de destino no existe o esta inactiva.',

            'notes.max' => 'Las notas no pueden superar los 1000 caracteres.',

            'items.required' => 'El traslado debe incluir al menos un ítem.',
            'items.array' => 'Los ítems deben ser un arreglo.',
            'items.min' => 'El traslado debe incluir al menos un ítem.',

            'items.*.inventory_id.required' => 'Cada ítem debe tener un inventario asociado.',
            'items.*.inventory_id.uuid' => 'El inventario del ítem debe ser un identificador válido.',
            'items.*.inventory_id.exists' => 'Uno de los inventarios seleccionados no existe.',
            'items.*.inventory_id.distinct' => 'El mismo inventario no puede repetirse en un traslado. Ya esta agregado en otra linea.',

            'items.*.requested_quantity.required' => 'La cantidad solicitada es obligatoria.',
            'items.*.requested_quantity.integer' => 'La cantidad solicitada debe ser un numero entero.',
            'items.*.requested_quantity.min' => 'La cantidad solicitada debe ser mayor a cero.',
        ];
    }
}
