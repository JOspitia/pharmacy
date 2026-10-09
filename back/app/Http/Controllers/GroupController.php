<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\GroupService;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Resources\GroupResource;
use Exception;

class GroupController extends Controller
{
    protected GroupService $groupService;

    /**
     * Inyectar las dependencias necesarias.
     */
    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    /**
     * Listado optimizado para selectores.
     */
    public function select()
    {
        $groups = Group::where('status', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    /**
     * Mostrar los grupos existentes
     */
    public function index()
    {
        return GroupResource::collection($this->groupService->getAllGroups());
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(StoreGroupRequest $request)
    {
        $group = $this->groupService->createGroup($request->validated());
        
        return response()->json([
            'message' => 'Grupo creado con éxito', 
            'data' => new GroupResource($group)
        ], 201);
    }

    /**
     * Mostrar un grupo seleccionado
     */
    public function show(Group $group)
    {
        return response()->json([
            'data' => new GroupResource($group)
        ], 200);
    }

    /**
     * Actualizar datos de un grupo existente
     */
    public function update(StoreGroupRequest $request, Group $group)
    {
        try {
            $updatedGroup = $this->groupService->updateGroup($group, $request->validated());

            return response()->json([
                'message' => 'Grupo actualizado con éxito', 
                'data' => new GroupResource($updatedGroup)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Cambiar estado del grupo
     */
    public function toggleStatus(Group $group)
    {
        try {
            $updatedGroup = $this->groupService->toggleStatus($group);
            $accion = $updatedGroup->status ? 'activado' : 'inactivado';

            return response()->json([
                'message' => "Grupo {$accion} con éxito", 
                'data' => new GroupResource($updatedGroup)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Validar que no tenga productos activos antes de eliminar
     */
    public function destroy(Group $group)
    {
        try {
            $this->groupService->deleteGroup($group);

            return response()->json(['message' => 'Grupo eliminado correctamente'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
