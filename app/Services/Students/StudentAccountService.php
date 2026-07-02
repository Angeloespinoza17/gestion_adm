<?php

namespace App\Services\Students;

use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentAccountService
{
    public function store(array $profilePayload, array $accountPayload = [], ?User $actor = null): StudentProfile
    {
        return DB::transaction(function () use ($profilePayload, $accountPayload, $actor) {
            $profilePayload['created_by'] = $actor?->id;
            $profilePayload['updated_by'] = $actor?->id;

            $student = StudentProfile::query()->create($profilePayload);
            $this->syncUser($student, $accountPayload, $actor);

            return $student->fresh();
        });
    }

    public function update(StudentProfile $student, array $profilePayload, array $accountPayload = [], ?User $actor = null): StudentProfile
    {
        return DB::transaction(function () use ($student, $profilePayload, $accountPayload, $actor) {
            $profilePayload['updated_by'] = $actor?->id;
            $student->update($profilePayload);
            $this->syncUser($student->fresh(), $accountPayload, $actor);

            return $student->fresh();
        });
    }

    private function syncUser(StudentProfile $student, array $accountPayload, ?User $actor = null): void
    {
        $user = $student->user ?: new User();
        $plainPassword = trim((string) ($accountPayload['password'] ?? ''));
        $defaultPassword = $student->rut ?: Str::random(20);

        $user->student_id = $student->id;
        $user->user_type = 'student';
        $user->name = $student->full_name;
        $user->email = $this->resolveUserEmail($student, $user);
        $user->active = array_key_exists('account_active', $accountPayload)
            ? (bool) $accountPayload['account_active']
            : ($user->exists ? (bool) $user->active : true);

        if (!$user->exists || $plainPassword !== '') {
            $user->password = Hash::make($plainPassword !== '' ? $plainPassword : $defaultPassword);
        }

        $user->save();

        $studentRole = Role::query()->firstWhere('slug', 'estudiante');
        if ($studentRole) {
            $user->roles()->syncWithoutDetaching([$studentRole->id]);
        }
    }

    private function resolveUserEmail(StudentProfile $student, User $user): string
    {
        $firstName = $this->normalizeEmailPart($this->firstToken($student->first_name));
        $lastName = $this->normalizeEmailPart($this->firstToken($student->last_name));
        $base = trim($firstName . '.' . $lastName, '.');

        if ($base === '') {
            $base = $student->rut
                ? str_replace(['.', '-'], '', strtolower($student->rut))
                : sprintf('student-%d', $student->id);
        }

        $email = sprintf('%s@cnscvaldivia.cl', $base);
        $counter = 1;

        while (
            User::query()
                ->where('email', $email)
                ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
                ->exists()
        ) {
            $email = sprintf('%s.%d@cnscvaldivia.cl', $base, $counter);
            $counter++;
        }

        return $email;
    }

    private function firstToken(?string $value): string
    {
        $tokens = preg_split('/\s+/', trim((string) $value)) ?: [];

        return (string) ($tokens[0] ?? '');
    }

    private function normalizeEmailPart(string $value): string
    {
        $normalized = Str::ascii(Str::lower($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?: '';

        return trim($normalized, '.');
    }
}
