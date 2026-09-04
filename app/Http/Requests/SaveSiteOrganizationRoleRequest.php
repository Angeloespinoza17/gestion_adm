<?php

namespace App\Http\Requests;

use App\Models\SiteOrganizationRole;
use App\Services\SiteOrganizationAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSiteOrganizationRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $role = $this->route('siteOrganizationRole');
        $bodyType = app(SiteOrganizationAccessService::class)->normalizeType(
            $this->input('organization_type'),
        );
        $routeType = app(SiteOrganizationAccessService::class)->normalizeType(
            $role?->organization_type,
        );
        $this->merge([
            'organization_type' => $role ? $routeType : $bodyType,
            '_type_mismatch' => $role && $bodyType && $routeType !== $bodyType,
        ]);
    }

    public function authorize(): bool
    {
        $role = $this->route('siteOrganizationRole');
        $type = $this->input('organization_type', $role?->organization_type);

        return app(SiteOrganizationAccessService::class)->canManage($this->user(), $type);
    }

    public function rules(): array
    {
        $role = $this->route('siteOrganizationRole');
        $type = app(SiteOrganizationAccessService::class)->normalizeType(
            $this->input('organization_type', $role?->organization_type),
        );

        return [
            'organization_type' => ['required', Rule::in(['cgpa', 'cde'])],
            '_type_mismatch' => ['declined'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('site_organization_roles', 'name')
                    ->where(fn ($query) => $query->where('organization_type', $type))
                    ->ignore($role?->id),
            ],
            'section' => ['required', Rule::in(SiteOrganizationRole::SECTIONS)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
