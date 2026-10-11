<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validaciones de ESTRUCTURA para resolver una discrepancia (HU-09, RN-07).
 *
 * RN-07: lo no recibido queda como discrepancia pendiente. Esta accion
 * la cierra, sin tocar Kardex (RN-06: el Kardex es inmutable, las
 * correcciones de inventario son por ajuste).
 *
 * Solo pide accion y nota. El estado de la discrepancia y la transicion
 * del traslado los valida TransferService::resolverDiscrepancia().
 */
class ResolveDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El middleware de permisos (module:transfers) ya se encargo del 403.
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                'in:resolve,write_off',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Debe indicar la accion: resolve o write_off.',
            'action.in' => 'La accion debe ser resolve (faltante llego por otro medio) o write_off (se asume la perdida).',
            'notes.max' => 'La nota no puede superar los 1000 caracteres.',
        ];
    }
}
