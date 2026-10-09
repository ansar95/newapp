<?php

namespace App\Support;

use App\Enums\ProjectDocumentStatus;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentStatusHistory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Read-only monitoring figures. Ages and durations are always derived from the status history, never stored.
 */
class ProjectDocumentMonitor
{
    /**
     * @param  Collection<int, ProjectDocument>  $documents
     */
    public function __construct(
        public readonly Collection $documents,
        public readonly CarbonInterface $now,
    ) {}

    public static function forProject(Project $project, ?CarbonInterface $now = null): self
    {
        return new self(self::loadDocuments($project->documents()->getQuery()), $now ?? now());
    }

    public static function forBilling(ClientBilling $billing, ?CarbonInterface $now = null): self
    {
        return new self(self::loadDocuments($billing->documents()->getQuery()), $now ?? now());
    }

    /**
     * @param  Builder<ProjectDocument>  $query
     * @return Collection<int, ProjectDocument>
     */
    private static function loadDocuments(Builder $query): Collection
    {
        return $query
            ->with([
                'documentType',
                'versions',
                'histories',
                'reference' => fn (MorphTo $morph) => $morph->morphWith([ClientBilling::class => ['term']]),
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, ProjectDocument>
     */
    public function activeDocuments(): Collection
    {
        return $this->documents->reject(fn (ProjectDocument $document) => $document->current_status === ProjectDocumentStatus::Cancelled);
    }

    public function total(): int
    {
        return $this->activeDocuments()->count();
    }

    public function submittedCount(): int
    {
        return $this->countStatus(ProjectDocumentStatus::Submitted) + $this->countStatus(ProjectDocumentStatus::Resubmitted);
    }

    public function underReviewCount(): int
    {
        return $this->countStatus(ProjectDocumentStatus::UnderReview);
    }

    public function revisionCount(): int
    {
        return $this->countStatus(ProjectDocumentStatus::RevisionRequired);
    }

    public function approvedCount(): int
    {
        return $this->countStatus(ProjectDocumentStatus::Approved);
    }

    public function draftCount(): int
    {
        return $this->countStatus(ProjectDocumentStatus::Draft);
    }

    public function waitingClientCount(): int
    {
        return $this->documents->filter(fn (ProjectDocument $document) => $document->current_status->isWaitingClient())->count();
    }

    public function waitingAdminCount(): int
    {
        return $this->documents->filter(fn (ProjectDocument $document) => $document->current_status->isWaitingAdmin())->count();
    }

    public function completedCount(): int
    {
        return $this->approvedCount();
    }

    public function longestWaitingClient(): ?ProjectDocument
    {
        return $this->documents
            ->filter(fn (ProjectDocument $document) => $document->current_status->isWaitingClient())
            ->sortByDesc(fn (ProjectDocument $document) => $document->waitingSeconds($this->now) ?? 0)
            ->first();
    }

    private function countStatus(ProjectDocumentStatus $status): int
    {
        return $this->documents->where('current_status', $status)->count();
    }

    /**
     * Time spent in each status, in history order. The current status keeps running until now unless it is final.
     *
     * @return list<array{history: ProjectDocumentStatusHistory, seconds: int|null, ongoing: bool}>
     */
    public static function statusDurations(ProjectDocument $document, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $histories = $document->histories->values();
        $segments = [];

        foreach ($histories as $index => $history) {
            $next = $histories->get($index + 1);

            if ($next !== null) {
                $segments[] = ['history' => $history, 'seconds' => self::secondsBetween($history->changed_at, $next->changed_at), 'ongoing' => false];

                continue;
            }

            $isFinal = $history->to_status->isLocked();

            $segments[] = [
                'history' => $history,
                'seconds' => $isFinal ? null : self::secondsBetween($history->changed_at, $now),
                'ongoing' => ! $isFinal,
            ];
        }

        return $segments;
    }

    /**
     * Total seconds per status across all cycles (e.g. two UNDER_REVIEW rounds are summed).
     *
     * @return array<string, int>
     */
    public static function totalSecondsByStatus(ProjectDocument $document, ?CarbonInterface $now = null): array
    {
        $totals = [];

        foreach (self::statusDurations($document, $now) as $segment) {
            if ($segment['seconds'] === null) {
                continue;
            }

            $status = $segment['history']->to_status->value;
            $totals[$status] = ($totals[$status] ?? 0) + $segment['seconds'];
        }

        return $totals;
    }

    /**
     * Seconds from the first submission until approval, or until now while still in progress.
     */
    public static function cycleSeconds(ProjectDocument $document, ?CarbonInterface $now = null): ?int
    {
        $start = $document->firstSubmittedAt();

        if ($start === null) {
            return null;
        }

        $end = $document->current_status->isLocked() ? $document->statusSince() : ($now ?? now());

        return self::secondsBetween($start, $end);
    }

    /**
     * Calendar days; anything shorter than a day is shown in hours.
     */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 3600) {
            return '< 1 jam';
        }

        if ($seconds < 86400) {
            return intdiv($seconds, 3600).' jam';
        }

        return intdiv($seconds, 86400).' hari';
    }

    /**
     * Upload and status events merged, newest first.
     *
     * @return Collection<int, array{
     *     type: 'status'|'upload',
     *     at: CarbonInterface,
     *     actor: string|null,
     *     status: ProjectDocumentStatus|null,
     *     from_status: ProjectDocumentStatus|null,
     *     version_number: int|null,
     *     notes: string|null,
     *     revision_reason: string|null,
     *     filename: string|null,
     *     duration_seconds: int|null,
     *     ongoing: bool
     * }>
     */
    public static function timeline(ProjectDocument $document, ?CarbonInterface $now = null): Collection
    {
        $document->loadMissing(['histories.changer', 'versions.uploader']);

        $statusEvents = collect(self::statusDurations($document, $now))->map(fn (array $segment) => [
            'type' => 'status',
            'at' => $segment['history']->changed_at,
            'actor' => $segment['history']->changer?->name,
            'status' => $segment['history']->to_status,
            'from_status' => $segment['history']->from_status,
            'version_number' => $segment['history']->version_number,
            'notes' => $segment['history']->notes,
            'revision_reason' => $segment['history']->revision_reason,
            'filename' => null,
            'duration_seconds' => $segment['seconds'],
            'ongoing' => $segment['ongoing'],
            'order' => [$segment['history']->from_status === null ? 0 : 2, $segment['history']->id],
        ]);

        $uploadEvents = $document->versions->map(fn ($version) => [
            'type' => 'upload',
            'at' => $version->uploaded_at,
            'actor' => $version->uploader?->name,
            'status' => null,
            'from_status' => null,
            'version_number' => $version->version_number,
            'notes' => $version->notes,
            'revision_reason' => null,
            'filename' => $version->original_filename,
            'duration_seconds' => null,
            'ongoing' => false,
            'order' => [1, $version->version_number],
        ]);

        return $statusEvents->concat($uploadEvents)
            ->sort(fn (array $a, array $b) => [$a['at']->getTimestamp(), ...$a['order']] <=> [$b['at']->getTimestamp(), ...$b['order']])
            ->reverse()
            ->map(function (array $event) {
                unset($event['order']);

                return $event;
            })
            ->values();
    }

    private static function secondsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) max(0, $from->diffInSeconds($to));
    }
}
