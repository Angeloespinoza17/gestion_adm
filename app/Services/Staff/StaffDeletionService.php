<?php

namespace App\Services\Staff;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffDeletionService
{
    public function deleteStaff(Staff $staff): void
    {
        $staffId = (int) $staff->getKey();

        DB::transaction(function () use ($staffId): void {
            $staff = Staff::query()->lockForUpdate()->findOrFail($staffId);
            $linkedUser = User::query()
                ->where('staff_id', $staffId)
                ->lockForUpdate()
                ->first();

            $linkedUser?->delete();
            $staff->dependencyReservations()->delete();
            $staff->delete();
        });

        $this->deleteStaffDirectories([$staffId]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{users: int, staff: int}
     */
    public function deleteUsers(Collection $users): array
    {
        $userIds = $users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $staffIds = DB::transaction(function () use ($userIds): array {
            $lockedUsers = User::query()
                ->whereIn('id', $userIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $deletedStaffIds = [];

            foreach ($userIds as $userId) {
                /** @var User|null $user */
                $user = $lockedUsers->get($userId);

                if (! $user) {
                    continue;
                }

                $staff = $user->staff()->lockForUpdate()->first();

                $user->delete();

                if ($staff) {
                    $staff->dependencyReservations()->delete();
                    $staff->delete();
                    $deletedStaffIds[] = (int) $staff->getKey();
                }
            }

            return $deletedStaffIds;
        });

        $this->deleteStaffDirectories($staffIds);

        return [
            'users' => $userIds->count(),
            'staff' => count($staffIds),
        ];
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    private function deleteStaffDirectories(array $staffIds): void
    {
        foreach (array_unique($staffIds) as $staffId) {
            Storage::disk('public')->deleteDirectory(sprintf('staff/%d', $staffId));
        }
    }
}
