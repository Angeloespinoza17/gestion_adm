<?php

namespace App\Enums\RiskPrevention;

enum RiskMatrixStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Observed = 'observed';
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Archived = 'archived';

    /** @return array<int, self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview],
            self::InReview => [self::Observed, self::Approved],
            self::Observed => [self::Draft, self::InReview],
            self::Approved => [self::Superseded, self::Archived],
            self::Superseded => [self::Archived],
            self::Archived => [],
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Observed], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }
}
