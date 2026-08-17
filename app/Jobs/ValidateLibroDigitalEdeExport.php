<?php

namespace App\Jobs;

use App\Models\LibroDigital\EdeExport;
use App\Models\User;
use App\Services\LibroDigital\EdeValidatorRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateLibroDigitalEdeExport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $exportId,
        public readonly int $actorId,
    ) {}

    public function handle(EdeValidatorRunner $runner): void
    {
        $export = EdeExport::query()->findOrFail($this->exportId);
        if (in_array($export->status->value, ['validated', 'released', 'stale', 'revoked'], true)) {
            return;
        }

        $runner->run($export, 'check', User::query()->findOrFail($this->actorId));
    }

    public function uniqueId(): string
    {
        return 'lcd-ede-validation-'.$this->exportId;
    }
}
