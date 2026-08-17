<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LibroDigitalAccessContext
{
    public function resolveSchool(Request $request, ?Book $book = null): School
    {
        $schoolId = $book?->school_id
            ?: $request->integer('school_id')
            ?: (int) $request->header('X-LCD-School-ID');

        $query = School::query()->where('active', true);
        if ($schoolId) {
            $query->whereKey($schoolId);
        }

        $user = $request->user();
        if (! $user) {
            throw ValidationException::withMessages([
                'school_id' => 'Se requiere una sesión autenticada para resolver el establecimiento.',
            ]);
        }
        if (! $user->isSuperAdmin()) {
            $query->whereHas('users', function (Builder $membership) use ($user): void {
                $today = Carbon::today()->toDateString();
                $membership->where('users.id', $user?->id)
                    ->where('lcd_school_users.active', true)
                    ->where(function (Builder $dates) use ($today): void {
                        $dates->whereNull('lcd_school_users.valid_from')->orWhere('lcd_school_users.valid_from', '<=', $today);
                    })
                    ->where(function (Builder $dates) use ($today): void {
                        $dates->whereNull('lcd_school_users.valid_to')->orWhere('lcd_school_users.valid_to', '>=', $today);
                    });
            });
        }

        $schools = $query->limit(2)->get();
        if ($schools->count() !== 1) {
            throw ValidationException::withMessages([
                'school_id' => $schools->isEmpty()
                    ? 'No tienes acceso vigente al establecimiento solicitado.'
                    : 'Selecciona explicitamente un establecimiento.',
            ]);
        }

        return $schools->firstOrFail();
    }

    public function canAccessSchool(User $user, int $schoolId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $today = Carbon::today()->toDateString();

        return $user->roles()->exists()
            && School::query()->whereKey($schoolId)->whereHas('users', function (Builder $query) use ($user, $today): void {
                $query->where('users.id', $user->id)
                    ->where('lcd_school_users.active', true)
                    ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_from')->orWhere('lcd_school_users.valid_from', '<=', $today))
                    ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_to')->orWhere('lcd_school_users.valid_to', '>=', $today));
            })->exists();
    }
}
