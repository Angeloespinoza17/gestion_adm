<?php

namespace App\Http\Controllers;

use App\Models\SystemModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function modules(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            $modules = SystemModule::query()
                ->where('active', true)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

            return response()->json(['data' => $modules]);
        }

        $directModules = SystemModule::query()
            ->where('active', true)
            ->whereHas('roles', function ($query) use ($user) {
                $query->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
            })
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

        $moduleIds = $directModules->pluck('id')->all();
        $parentIds = $directModules->pluck('parent_id')->filter()->unique()->values()->all();

        while (!empty($parentIds)) {
            $parents = SystemModule::query()
                ->where('active', true)
                ->whereIn('id', $parentIds)
                ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

            foreach ($parents as $parent) {
                if (!in_array($parent->id, $moduleIds, true)) {
                    $moduleIds[] = $parent->id;
                }
            }

            $parentIds = $parents->pluck('parent_id')->filter()->unique()->values()->all();
        }

        $modules = SystemModule::query()
            ->whereIn('id', $moduleIds)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

        return response()->json(['data' => $modules]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'data' => $user->isSuperAdmin()
                ? array_values(array_unique(array_merge(['__superadmin__'], $user->permissionSlugs())))
                : $user->permissionSlugs(),
        ]);
    }
}
