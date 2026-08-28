<?php

namespace App\NativeComponents;

use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * AI Goal Breakdown proposals (P27-006): mobile accept/reject for pending
 * goal-breakdown proposals. The server holds the proposal + ledger; here the
 * owner reviews milestone titles and decision is written back via
 * /ai/proposals/{id}/accept|reject. A proposal is never auto-committed.
 */
final class BreakdownScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public string $notice = '';

    public int $goalId = 0;

    public array $proposals = [];

    public function mount(): void
    {
        $this->goalId = (int) $this->param('goalId', 0);
        $this->reload();
    }

    public function reload(): void
    {
        $this->state = 'loading';
        $this->refreshOffline();

        if (! KinevoApi::authed()) {
            $this->state = 'unauthorized';

            return;
        }
        if ($this->offline) {
            $this->state = 'offline';

            return;
        }

        $res = KinevoApi::get('/ai/proposals', [
            'proposal_type' => 'goal_breakdown',
            'decision' => 'pending',
            'limit' => 50,
        ]);

        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if (! $res->successful()) {
            $this->state = 'error';
            $this->error = 'Could not load proposals.';

            return;
        }

        $all = $res->json('proposals') ?? [];
        $goalId = $this->goalId;
        $this->proposals = array_values(array_filter(
            $all,
            static fn ($p) => (int) ($p['payload']['goal_id'] ?? 0) === $goalId,
        ));

        $this->state = 'ready';
    }

    /** Milestone title preview for a proposal payload. */
    public function previewTitles(array $payload): array
    {
        return array_map(
            static fn ($m) => $m['title'] ?? 'Untitled milestone',
            $payload['milestones'] ?? [],
        );
    }

    public function accept(int $proposalId): void
    {
        $res = KinevoApi::post('/ai/proposals/'.$proposalId.'/accept', []);
        $this->handleDecision($res, 'Accepted — milestones created.');
    }

    public function reject(int $proposalId): void
    {
        $res = KinevoApi::post('/ai/proposals/'.$proposalId.'/reject', []);
        $this->handleDecision($res, 'Rejected.');
    }

    public function backToGoals(): void
    {
        $this->replace('/goals');
    }

    private function handleDecision($res, string $successMessage): void
    {
        if ($res->successful()) {
            $this->notice = $successMessage;
            $this->error = '';
            $this->reload();

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if ($res->status() === 403 && $res->json('code') === 'ENTITLEMENT_LIMIT') {
            $this->state = 'entitlement';
            $this->error = 'Plan limit reached — upgrade on the web app to keep using AI.';

            return;
        }
        if ($res->json('code') === 'AI_PROVIDER_UNAVAILABLE' || $res->status() === 503) {
            $this->state = 'error';
            $this->error = 'AI is not ready — check provider on the web app.';

            return;
        }
        if ($res->status() === 422) {
            $this->state = 'error';
            $this->error = Str::start((string) $res->json('error', 'Could not update proposal.'), 'Blocked: ');

            return;
        }
        $this->state = 'error';
        $this->error = 'Update failed — retry.';
    }

    public function render(): View
    {
        return view('breakdown', ['screen' => $this]);
    }
}
