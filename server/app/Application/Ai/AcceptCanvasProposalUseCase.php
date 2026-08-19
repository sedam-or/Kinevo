<?php

namespace App\Application\Ai;

use App\Application\Canvas\CreateCanvasUseCase;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Canvas\Canvas;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Accept a pending canvas generation proposal and create the Canvas (FR-62,
 * §13.3 CanvasProposal). The proposal's title becomes the Canvas title; the
 * validated sections are returned as starting content — the Excalidraw scene
 * serialization stays an editor/UI concern (external engine boundary).
 */
final readonly class AcceptCanvasProposalUseCase
{
    public function __construct(
        private AiProposalRepository $proposals,
        private CreateCanvasUseCase $createCanvas,
    ) {}

    public function __invoke(int $userId, int $proposalId): Canvas
    {
        $proposal = $this->proposals->findForUser($userId, $proposalId);

        if ($proposal === null) {
            throw new InvalidArgumentException('AI proposal not found.');
        }

        if (! $proposal->isPending()) {
            throw new InvalidArgumentException('AI proposal is not pending.');
        }

        if (! $proposal->type->equals(new AiProposalType(AiProposalType::CANVAS))) {
            throw new InvalidArgumentException('Proposal is not a canvas proposal.');
        }

        return DB::transaction(function () use ($userId, $proposal) {
            $canvas = $this->createCanvas->__invoke($userId, $proposal->payload['title']);

            $this->proposals->updateDecision(
                $proposal->withDecision('accepted', "canvas:create:{$proposal->id}"),
            );

            return $canvas;
        });
    }
}
