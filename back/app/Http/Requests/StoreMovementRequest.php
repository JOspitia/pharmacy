<?php

namespace App\Http\Requests;

use App\Models\Lot;
use App\Models\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Rules\Rule;
use Illuminate\Validation\Rule as ValidationRule;

/**
 * Validaciones para registrar un movimiento del Kardex (HU-08 §10).
 *
 * Se valida en tres capas para que cada capa tenga su propia responsabilidad:
 *
 *   1. StoreMovementRequest: existencia de IDs, tipo valido, cantidad > 0,
 *      largo de motivo, referencia consistente, vencimiento para ENTRADA.
 *   2. KardexService: stock suficiente, warehouse consistente con la existencia.
 *   3. Base de datos: CHECK quantity >= 0 (ultimo muro).
 *
 * La validacion de existence de warehouse vs. la existencia resuelta (KE-03)
 * vive en el controller y no aqui porque requiere acceder a la existencia
 * resuelta por firstOrCreate, que es logica de dominio y no de request.
 */
class StoreMovementRequest extends FormRequest
{
    /**
     * Tipos de movimiento validos (identificadores tecnicos en ingles).
     *
     * HU-08 §10 exige que el type vaya en mayuscula. Se valida contra la
     * tabla movement_types en vez de hardcodear: si se agrega un tipo nuevo
     * en la base, esta validacion lo acepta automaticamente sin tocar codigo.
     */
    private ?array $tiposValidos = null;

    public function authorize(): bool
    {
        // El middleware de permisos (module:kardex) ya se encargo del 403.
        return true;
    }

    public function rules(): array
    {
        return [
            'lot_id' => [
                'required',
                'uuid',
                ValidationRule::exists('lots', 'id')->where(fn ($query) => $query->where('status', true)),
            ],

            'warehouse_id' => [
                'required',
                'uuid',
                ValidationRule::exists('bodegas', 'id')->where(fn ($query) => $query->where('status', true)),
            ],

            'type' => [
                'required',
                'string',
                'max:30',
                ValidationRule::in($this->tiposValidos()),
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],

            'reference_type' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $this->reference_id === null) {
                        $fail('reference_id es obligatorio cuando se define reference_type.');
                    }
                },
            ],

            'reference_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $this->reference_type === null) {
                        $fail('reference_type es obligatorio cuando se define reference_id.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'lot_id.required' => 'El lote es obligatorio.',
            'lot_id.uuid' => 'El lote debe ser un identificador valido.',
            'lot_id.exists' => 'El lote seleccionado no existe o esta inactivo.',

            'warehouse_id.required' => 'La bodega es obligatoria.',
            'warehouse_id.uuid' => 'La bodega debe ser un identificador valido.',
            'warehouse_id.exists' => 'La bodega seleccionada no existe o esta inactiva.',

            'type.required' => 'El tipo de movimiento es obligatorio.',
            'type.in' => 'El tipo de movimiento no es valido.',

            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un numero entero.',
            'quantity.min' => 'La cantidad debe ser mayor a cero.',

            'reason.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }

    /**
     * Validaciones que requieren acceder al lote cargado (exists ya verifico que existe).
     *
     * KE-09: una ENTRADA con lote vencido se rechaza aqui. El CHECK de la base
     * no alcanza porque el CHECK solo aplica a quantity, no a expiration_date.
     * Es la unica validacion de esta lista que requiere acceso al modelo.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tipo = $this->input('type');

            // KE-09: ENTRADA con lote vencido
            if ($tipo === 'ENTRY') {
                $lote = Lot::find($this->input('lot_id'));

                if ($lote && $lote->expiration_date !== null) {
                    $hoy = \Illuminate\Support\Carbon::today();

                    if ($lote->expiration_date->lt($hoy)) {
                        $validator->errors()->add(
                            'lot_id',
                            'No se puede registrar entrada de un lote vencido.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Tipos de movimiento validos segun la tabla movement_types.
     *
     * Se consulta una sola vez por request y se cachea en memoria del objeto.
     */
    private function tiposValidos(): array
    {
        if ($this->tiposValidos === null) {
            $this->tiposValidos = MovementType::pluck('name')->all();
        }

        return $this->tiposValidos;
    }
}
