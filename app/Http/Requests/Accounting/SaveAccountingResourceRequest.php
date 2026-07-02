<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingResourceRegistry;
use Illuminate\Foundation\Http\FormRequest;

class SaveAccountingResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resource = (string) $this->route('resource');
        $registry = app(AccountingResourceRegistry::class);
        $accessService = app(AccountingAccessService::class);

        return $accessService->canManage(
            $this->user(),
            $registry->permissionFor($resource, 'manage_permission')
        );
    }

    public function rules(): array
    {
        $resource = (string) $this->route('resource');
        $recordId = $this->route('record');
        $recordId = is_object($recordId) ? $recordId->getKey() : (is_numeric($recordId) ? (int) $recordId : null);

        $config = app(AccountingResourceRegistry::class)->get($resource);
        $rules = $config['rules'] ?? fn () => [];

        return $rules($recordId);
    }
}
