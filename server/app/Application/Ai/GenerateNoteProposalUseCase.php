<?php

namespace App\Application\Ai;

use App\Application\Knowledge\GetNoteUseCase;
use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\AiSchemaRegistry;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\Entities\AiRun;
use App\Domain\Ai\StructuredAiOutputParser;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\ValidatedAiProposal;
use App\Domain\Knowledge\Note;
use InvalidArgumentException;

/**
 * Generate a note-driven proposal (summary or task extraction; SRS §13.3,
 * §13.4, FR-62).
 *
 * Context selection is minimal and owner-scoped (SRS §13.4): only the
 * requested note's plain-text content is sent, bounded by the configured
 * prompt budget. The validated proposal is persisted as PENDING; task
 * extraction creates Tasks only after the user accepts (FR-62) — never here.
 */
final readonly class GenerateNoteProposalUseCase
{
    public function __construct(
        private GetNoteUseCase $getNote,
        private AiOrchestrator $ai,
        private AiProviderResolver $resolver,
        private AiSchemaRegistry $registry,
        private StructuredAiOutputParser $parser,
        private AiRunRepository $runs,
        private AiProposalRepository $proposals,
        private AiCreditGuard $credits,
    ) {}

    public function __invoke(
        int $userId,
        int $noteId,
        AiProposalType $type,
        ?string $instructions = null,
    ): AiProposal {
        $note = $this->getNote->__invoke($userId, $noteId);

        $prompt = $this->buildPrompt($note, $type, $instructions);

        $validated = $this->generate($userId, $type, $prompt);

        return $this->proposals->persist(AiProposal::pending(
            $userId,
            $type,
            $validated->schemaVersion,
            $validated->payload,
        ));
    }

    private function buildPrompt(Note $note, AiProposalType $type, ?string $instructions): string
    {
        $content = trim($note->plainTextCache ?? '');
        $budget = (int) config('ai.max_prompt_chars', 8000);
        if (mb_strlen($content) > $budget) {
            $content = mb_substr($content, 0, $budget);
        }

        $verb = $type->value === AiProposalType::SUMMARY
            ? 'summarize the following note'
            : 'extract actionable tasks from the following note';

        $prefix = trim($instructions ?? '') !== ''
            ? "{$instructions}\n\n"
            : '';

        return "{$prefix}Please {$verb}.\n\nNote title: {$note->title}\n\nNote content:\n{$content}";
    }

    private function generate(int $userId, AiProposalType $type, string $prompt): ValidatedAiProposal
    {
        $started = hrtime(true);
        $provider = $this->resolver->resolve($userId);
        $contextHash = hash('sha256', $prompt);
        $schemaVersion = $this->registry->versionFor($type);
        $byok = $this->resolver->isUserProvided($userId);
        $requestId = $this->credits->begin($userId, $byok);

        try {
            $response = $this->ai->generate($userId, new AiRequest($type->role(), $prompt));

            $proposal = $this->parser->parse($type, $response->text);

            $this->credits->recordSuccess(
                $userId,
                $byok,
                $requestId,
                $response,
                $type->value,
                $schemaVersion,
                $contextHash,
            );

            return $proposal;
        } catch (AiProviderException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiProviderException::CODE_UNAVAILABLE, $requestId);

            throw $e;
        } catch (AiOutputException|InvalidArgumentException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiOutputException::CODE_INVALID, $requestId);

            throw $e;
        }
    }

    private function recordFailure(
        int $userId,
        string $providerName,
        string $providerModel,
        AiProposalType $type,
        int $schemaVersion,
        string $contextHash,
        int $started,
        string $errorCode,
        ?string $requestId = null,
    ): void {
        $this->runs->record(AiRun::failed(
            $userId,
            $providerName,
            $providerModel,
            $type->value,
            $schemaVersion,
            $contextHash,
            (int) ((hrtime(true) - $started) / 1_000_000),
            $errorCode,
            null,
            $requestId,
        ));
    }
}
