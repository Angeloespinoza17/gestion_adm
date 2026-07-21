<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleImpersonationController extends Controller
{
    public function switch(Request $request, string $roleSlug): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor && $actor->active && $actor->isSuperAdmin(), 403);

        $role = Role::query()
            ->where('active', true)
            ->where('slug', $this->normalizeRoleSlug($roleSlug))
            ->firstOrFail();

        $target = User::query()
            ->where('active', true)
            ->where('email', $this->emailForRole($role))
            ->firstOrFail();

        $token = $target->createToken("impersonated-by-{$actor->id}")->plainTextToken;

        logger()->info('Role impersonation token issued.', [
            'actor_user_id' => $actor->id,
            'actor_email' => $actor->email,
            'target_user_id' => $target->id,
            'target_email' => $target->email,
            'role_slug' => $role->slug,
        ]);

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'email' => $target->email,
                ],
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ],
                'token' => $token,
                'impersonated_by' => [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'email' => $actor->email,
                ],
            ],
        ]);
    }

    private function normalizeRoleSlug(string $roleSlug): string
    {
        $slug = Str::lower(Str::ascii($roleSlug));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?: $roleSlug;

        return trim($slug, '_');
    }

    private function emailForRole(Role $role): string
    {
        return "superadmin_{$role->slug}@cnscgestion.cl";
    }
}
