<?php

namespace App\Enums;

enum ProjectDocumentStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case RevisionRequired = 'REVISION_REQUIRED';
    case Resubmitted = 'RESUBMITTED';
    case Approved = 'APPROVED';
    case Cancelled = 'CANCELLED';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::RevisionRequired => 'Revision Required',
            self::Resubmitted => 'Resubmitted',
            self::Approved => 'Approved',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Arsip',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::UnderReview, self::Cancelled],
            self::UnderReview => [self::Approved, self::RevisionRequired],
            self::RevisionRequired => [self::Resubmitted, self::Cancelled],
            self::Resubmitted => [self::UnderReview],
            self::Approved, self::Cancelled, self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * The client holds the document: submitted, resubmitted, or being reviewed.
     */
    public function isWaitingClient(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::Resubmitted], true);
    }

    /**
     * The client asked for a revision and the admin has to act.
     */
    public function isWaitingAdmin(): bool
    {
        return $this === self::RevisionRequired;
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Approved, self::Cancelled], true);
    }

    /**
     * Versions may only be uploaded while the admin holds the document.
     */
    public function acceptsNewVersion(): bool
    {
        return in_array($this, [self::Draft, self::RevisionRequired, self::Archived], true);
    }
}
