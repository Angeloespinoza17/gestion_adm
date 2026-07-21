<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->profilePayload($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $user->update(['name' => $payload['name']]);

        if ($request->boolean('remove_photo')) {
            $this->deleteProfilePhoto($user);
            $user->forceFill(['profile_photo_path' => null])->save();
        }

        if ($request->file('photo') instanceof UploadedFile) {
            $this->storeProfilePhoto($user, $request->file('photo'));
        }

        return response()->json([
            'message' => 'Ficha actualizada correctamente.',
            'data' => $this->profilePayload($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($payload['current_password'], $user->password)) {
            return response()->json([
                'message' => 'La contrasena actual no coincide.',
                'errors' => [
                    'current_password' => ['La contrasena actual no coincide.'],
                ],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($payload['password']),
        ]);

        return response()->json([
            'message' => 'Contrasena actualizada correctamente.',
        ]);
    }

    private function profilePayload(User $user): array
    {
        $user->loadMissing('cargo:id,name,slug', 'roles:id,name,slug', 'staff:id,full_name,rut,profile_photo_path');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'profile_photo_url' => $user->profile_photo_url,
            'cargo' => $user->cargo ? [
                'id' => $user->cargo->id,
                'name' => $user->cargo->name,
                'slug' => $user->cargo->slug,
            ] : null,
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
            'staff' => $user->staff ? [
                'id' => $user->staff->id,
                'full_name' => $user->staff->full_name,
                'rut' => $user->staff->rut,
                'position' => $user->cargo?->name,
            ] : null,
        ];
    }

    private function storeProfilePhoto(User $user, UploadedFile $photo): void
    {
        $this->deleteProfilePhoto($user);

        $path = $photo->storePubliclyAs(
            sprintf('users/%d/profile', $user->id),
            now()->format('Ymd_His') . '_' . uniqid() . '.' . $photo->getClientOriginalExtension(),
            'public',
        );

        $user->forceFill(['profile_photo_path' => $path])->save();
    }

    private function deleteProfilePhoto(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
    }
}
