<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Services\ReportService;

/**
 * Controlador para los sensores de monitoreo ambiental.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service)
    {
    }

    /**
     * Reporte de inventario con filtros, ordenamiento y paginación.
     */
    public function index(ReportRequest $request)
    {
        $report = $this->service->getInventario($request->validated());
        return ReportResource::collection($report);
    }
}
