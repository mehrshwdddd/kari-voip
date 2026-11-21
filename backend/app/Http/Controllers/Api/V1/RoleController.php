<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    public function roles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'sanctum')->with('permissions')->get()->map(function ($role) {
            $enum = RoleEnum::tryFrom($role->name);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $enum ? $enum->label() : $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json([
            'roles' => $roles,
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'sanctum')->get()->pluck('name');

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}
