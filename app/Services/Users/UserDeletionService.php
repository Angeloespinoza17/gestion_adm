<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\Staff\StaffDeletionService;
use App\Services\Students\StudentDeletionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserDeletionService
{
    public function __construct(
        private readonly StaffDeletionService $staffDeletionService,
        private readonly StudentDeletionService $studentDeletionService,
    ) {}

    /**
     * @param  Collection<int, User>  $users
     * @return array{users: int, staff: int, students: int}
     */
    public function deleteUsers(Collection $users): array
    {
        $userIds = $users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $result = DB::transaction(function () use ($userIds): array {
            $lockedUsers = User::query()
                ->whereIn('id', $userIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $deletedStaffIds = [];
            $deletedStudentCount = 0;
            $deletedUserCount = 0;

            foreach ($userIds as $userId) {
                /** @var User|null $user */
                $user = $lockedUsers->get($userId);

                if (! $user) {
                    continue;
                }

                $staff = $user->staff()->lockForUpdate()->first();
                $student = $user->student()->lockForUpdate()->first();

                $user->delete();
                $deletedUserCount++;

                if ($staff) {
                    $this->staffDeletionService->deleteStaffRecord($staff);
                    $deletedStaffIds[] = (int) $staff->getKey();
                }

                if ($student) {
                    $this->studentDeletionService->deleteStudentRecord($student);
                    $deletedStudentCount++;
                }
            }

            return [
                'users' => $deletedUserCount,
                'staff' => count($deletedStaffIds),
                'students' => $deletedStudentCount,
                'staff_ids' => $deletedStaffIds,
            ];
        });

        $this->staffDeletionService->cleanupDirectories($result['staff_ids']);
        unset($result['staff_ids']);

        return $result;
    }
}
