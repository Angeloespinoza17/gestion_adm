<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Exceptions\LibroDigital\VersionConflictException;
use App\Http\Requests\LibroDigital\UpdateConfigurationRequest;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\Setting;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CompliancePreflightService;
use App\Services\LibroDigital\FeatureFlagService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigurationController extends LibroDigitalController
{
    private const FLAGS = [
        'lcd_enabled' => 'lcd_enabled',
        'parvularia_enabled' => 'lcd_parvularia_enabled',
        'identity_features_enabled' => 'lcd_identity_verifier_enabled',
        'ede_exports_enabled' => 'lcd_ede_export_enabled',
        'sige_integration_enabled' => 'lcd_sige_reconciliation_enabled',
        'fiscalization_mode_enabled' => 'lcd_fiscalization_download_enabled',
    ];

    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly FeatureFlagService $features,
        private readonly AuditEventWriter $audit,
        private readonly CompliancePreflightService $preflight,
    ) {
        parent::__construct($access);
    }

    public function show(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $setting = $this->setting($school->id);

        return $this->dataResponse($this->payload($school, $setting), version: (int) ($setting?->value['lock_version'] ?? 1));
    }

    public function update(UpdateConfigurationRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $setting = $this->setting($school->id);
        $actual = (int) ($setting?->value['lock_version'] ?? 1);
        $expected = (int) ($request->header('If-Match') ?: $request->input('lock_version'));
        if ($actual !== $expected) {
            throw new VersionConflictException($expected, $actual);
        }
        $data = $request->validated();
        $this->assertDesiredFlagsReady($school->id, $data['feature_flags']);
        $next = $actual + 1;
        $scopeKey = 'school:'.$school->id;

        DB::transaction(function () use ($request, $school, $setting, $data, $next, $scopeKey): void {
            $value = [
                'timezone' => $data['timezone'],
                'attendance_autosave_seconds' => $data['attendance_autosave_seconds'],
                'lock_version' => $next,
            ];
            if ($setting) {
                $setting->forceFill(['academic_year_id' => $data['academic_year_id'] ?? null, 'value' => $value, 'updated_by' => $request->user()->id])->save();
            } else {
                Setting::query()->create([
                    'school_id' => $school->id, 'academic_year_id' => $data['academic_year_id'] ?? null,
                    'scope_key' => $scopeKey, 'key' => 'configuration', 'value' => $value,
                    'is_encrypted' => false, 'updated_by' => $request->user()->id,
                ]);
            }
            $school->forceFill(['timezone' => $data['timezone'], 'updated_by' => $request->user()->id])->save();
            foreach (self::FLAGS as $clientKey => $code) {
                if (! array_key_exists($clientKey, $data['feature_flags'])) {
                    continue;
                }
                FeatureFlag::query()->updateOrCreate(
                    ['scope_key' => $scopeKey, 'code' => $code],
                    ['school_id' => $school->id, 'enabled' => (bool) $data['feature_flags'][$clientKey], 'configuration' => null, 'updated_by' => $request->user()->id]
                );
            }
        }, 3);

        $fresh = $this->setting($school->id);
        $payload = $this->payload($school->fresh(), $fresh);
        $this->audit->write('lcd.configuration.updated', 'update', $fresh, actor: $request->user(), schoolId: $school->id, academicYearId: $fresh?->academic_year_id, after: $payload, request: $request);

        return $this->dataResponse($payload, version: $next);
    }

    private function setting(int $schoolId): ?Setting
    {
        return Setting::query()->where('scope_key', 'school:'.$schoolId)->where('key', 'configuration')->first();
    }

    /** @param array<string, bool> $desired */
    private function assertDesiredFlagsReady(int $schoolId, array $desired): void
    {
        $result = $this->preflight->run($schoolId);
        $requirements = [
            'lcd_enabled' => ['ready' => (bool) ($result['core_ready'] ?? false), 'capability' => 'core'],
            'identity_features_enabled' => ['ready' => (bool) data_get($result, 'capabilities.identity.ready', false), 'capability' => 'identity'],
            'ede_exports_enabled' => ['ready' => (bool) data_get($result, 'capabilities.ede.ready', false), 'capability' => 'ede'],
            'parvularia_enabled' => ['ready' => (bool) data_get($result, 'capabilities.parvularia.ready', false), 'capability' => 'parvularia'],
            'sige_integration_enabled' => ['ready' => (bool) data_get($result, 'capabilities.sige.ready', false), 'capability' => 'sige'],
            'fiscalization_mode_enabled' => ['ready' => (bool) data_get($result, 'capabilities.fiscalization.ready', false), 'capability' => 'fiscalization'],
        ];

        foreach ($requirements as $clientKey => $requirement) {
            if (! ($desired[$clientKey] ?? false) || $requirement['ready']) {
                continue;
            }
            $details = collect($result['checks'] ?? [])->where('capability', $requirement['capability'])
                ->where('configured', false)->map(fn (array $check) => [
                    'field' => 'feature_flags.'.$clientKey,
                    'reason' => $check['remediation'],
                    'check' => $check['code'],
                ])->values()->all();
            throw new LibroDigitalException(
                'La capacidad solicitada no supera el preflight de cumplimiento.',
                'LCD_CONFIGURATION_CAPABILITY_NOT_READY',
                422,
                $details,
            );
        }
    }

    /** @return array<string, mixed> */
    private function payload(School $school, ?Setting $setting): array
    {
        $canonical = $this->features->all($school->id);
        $flags = collect(self::FLAGS)->mapWithKeys(fn (string $code, string $clientKey) => [$clientKey => (bool) ($canonical[$code] ?? false)])->all();
        $value = $setting?->value ?? [];

        return [
            'school_id' => $school->id,
            'academic_year_id' => $setting?->academic_year_id,
            'timezone' => $value['timezone'] ?? $school->timezone,
            'attendance_autosave_seconds' => (int) ($value['attendance_autosave_seconds'] ?? 10),
            'feature_flags' => $flags,
            'lock_version' => (int) ($value['lock_version'] ?? 1),
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }
}
