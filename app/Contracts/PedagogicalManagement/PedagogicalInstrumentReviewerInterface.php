<?php

namespace App\Contracts\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;

interface PedagogicalInstrumentReviewerInterface
{
    public function isConfigured(): bool;

    public function provider(): string;

    public function model(): string;

    public function promptVersion(): string;

    /**
     * @return array{
     *   summary:string,
     *   errors:list<array<string,mixed>>,
     *   suggestions:list<array<string,mixed>>,
     *   findings?:list<array<string,mixed>>,
     *   provider_response_id:?string,
     *   model:string,
     *   usage:array<string,mixed>,
     *   extracted_text?:string,
     *   normalized_text?:string,
     *   extracted_data?:array<string,mixed>
     * }
     */
    public function review(PedagogicalInstrument $instrument, PedagogicalInstrumentFile $file, string $absolutePath): array;
}
