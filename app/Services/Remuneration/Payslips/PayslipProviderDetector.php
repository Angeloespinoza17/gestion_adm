<?php

namespace App\Services\Remuneration\Payslips;

use App\Services\Remuneration\Payslips\Contracts\PayslipParserInterface;
use Illuminate\Validation\ValidationException;

class PayslipProviderDetector
{
    /** @var array<int,PayslipParserInterface> */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = collect(config('remuneration_payslips.parsers', [NumerusPayslipParser::class]))
            ->map(fn (string $parser): object => app($parser))
            ->filter(fn (object $parser): bool => $parser instanceof PayslipParserInterface)
            ->values()
            ->all();
    }

    public function detect(array $page): PayslipParserInterface
    {
        $ranked = collect($this->parsers)
            ->map(fn (PayslipParserInterface $parser): array => ['parser' => $parser, 'confidence' => $parser->confidence($page)])
            ->sortByDesc('confidence')
            ->values();

        $match = $ranked->first();
        if (! $match || $match['confidence'] < 0.5) {
            throw ValidationException::withMessages([
                'file' => 'No fue posible detectar un formato de liquidación compatible para una de las páginas.',
            ]);
        }

        return $match['parser'];
    }
}
