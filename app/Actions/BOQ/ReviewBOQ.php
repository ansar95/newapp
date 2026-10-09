<?php

namespace App\Actions\BOQ;

use App\Enums\ReviewDecision;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqRevision;
use App\Models\User;

class ReviewBOQ
{
    public function __construct(
        private ConfirmBOQ $confirmBOQ,
        private RequestRevision $requestRevision,
    ) {}

    /**
     * Review a BOQ waiting for approval.
     *
     * @param  array{
     *     reason?: string,
     *     item_ids?: list<int>,
     *     item_reasons?: array<int, string>,
     *     review_notes?: string|null
     * }  $payload
     */
    public function handle(
        User $actor,
        Boq $boq,
        ReviewDecision|string $decision,
        array $payload = [],
    ): Boq|BoqRevision {
        $decision = $decision instanceof ReviewDecision
            ? $decision
            : ReviewDecision::from($decision);

        return match ($decision) {
            ReviewDecision::Confirm => $this->confirmBOQ->handle(
                $actor,
                $boq,
                $payload['review_notes'] ?? null,
            ),
            ReviewDecision::RequestRevision => $this->handleRequestRevision(
                $actor,
                $boq,
                $payload,
            ),
        };
    }

    /**
     * @param  array{
     *     reason?: string,
     *     item_ids?: list<int>,
     *     item_reasons?: array<int, string>
     * }  $payload
     */
    private function handleRequestRevision(User $actor, Boq $boq, array $payload): BoqRevision
    {
        if (! isset($payload['reason'], $payload['item_ids'])) {
            throw BusinessRuleException::make(
                'Request revision wajib memiliki alasan dan minimal satu item.'
            );
        }

        return $this->requestRevision->handle($actor, $boq, [
            'reason' => $payload['reason'],
            'item_ids' => $payload['item_ids'],
            'item_reasons' => $payload['item_reasons'] ?? [],
        ]);
    }
}
