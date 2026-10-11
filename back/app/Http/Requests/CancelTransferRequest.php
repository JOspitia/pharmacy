<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validaciones de ESTRUCTURA para anular un traslado (HU-09, RN-07).
 *
 * Solo pide el motivo de la anulacion, que queda persistido en
 * notes (o en un campo dedicated si se agrega en el futuro). El
 * estado del traslado y la reversibilidad los valida
 * TransferService::cancel() (is_cancellable segun el status_id).
 *
 * Si el body viene vacio, se acepta igualmente: la anulacion sin
 * motivo es valida (puede ser "el sistema rechazo automaticamente"
 * por una validacion previa). Pero si viene, el campo es
 * obligatorio.
 */
class CancelTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El middleware de permisos (module:transfers) ya se encargo del 403.
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.max' => 'El motivo de la anulacion no puede superar los 1000 caracteres.',
        ];
    }
}
