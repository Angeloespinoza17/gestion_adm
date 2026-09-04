<?php

namespace App\Services;

use App\Models\User;

class SiteOrganizationAccessService
{
    private const VIEW_PERMISSIONS = [
        'cgpa' => 'ver_cgpa_sitio',
        'cde' => 'ver_cde_sitio',
        'joint_committee' => 'ver_comite_paritario_sitio',
    ];

    private const MANAGE_PERMISSIONS = [
        'cgpa' => 'gestionar_cgpa_sitio',
        'cde' => 'gestionar_cde_sitio',
        'joint_committee' => 'gestionar_comite_paritario_sitio',
    ];

    public function normalizeType(?string $type): string
    {
        return str_replace('-', '_', trim(strtolower((string) $type)));
    }

    public function isSupportedType(?string $type): bool
    {
        return array_key_exists($this->normalizeType($type), self::VIEW_PERMISSIONS);
    }

    public function canView(?User $user, ?string $type): bool
    {
        $permission = self::VIEW_PERMISSIONS[$this->normalizeType($type)] ?? null;

        return $permission !== null && ($user?->hasPermission($permission) ?? false);
    }

    public function canManage(?User $user, ?string $type): bool
    {
        $permission = self::MANAGE_PERMISSIONS[$this->normalizeType($type)] ?? null;

        return $permission !== null && ($user?->hasPermission($permission) ?? false);
    }

    public function viewPermission(string $type): ?string
    {
        return self::VIEW_PERMISSIONS[$this->normalizeType($type)] ?? null;
    }

    public function managePermission(string $type): ?string
    {
        return self::MANAGE_PERMISSIONS[$this->normalizeType($type)] ?? null;
    }
}
