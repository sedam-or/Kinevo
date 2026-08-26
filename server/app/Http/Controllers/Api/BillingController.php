<?php

namespace App\Http\Controllers\Api;

use App\Application\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Billing\MidtransGateway;
use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use App\Models\SaasSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * TASK-P24-010/013..016 — billing API surface. The browser is never
 * authoritative for payment success: only a verified provider webhook
 * transitions subscription state.
 */
final class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly MidtransGateway $midtrans,
    ) {}

    /** TASK-P24-010/011 — idempotent checkout creation (authenticated). */
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_code' => ['required', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->billing->startCheckout(
                (int) $request->user()->id,
                (string) $validator->validated()['plan_code'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            // Gateway unavailable/error must fail clearly without local state.
            return response()->json(['error' => $e->getMessage(), 'code' => 'GATEWAY_ERROR'], 502);
        }

        return response()->json($result, 201);
    }

    /** TASK-P24-025 — safe billing snapshot for settings UX. */
    public function subscription(Request $request): JsonResponse
    {
        $row = BillingSubscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'subscription' => $row === null ? null : [
                'plan_code' => $row->plan_code,
                'status' => $row->state,
                'price_amount_minor' => $row->price_amount_minor,
                'currency' => $row->price_currency,
                'uncertain' => $row->uncertain,
            ],
            'transactions' => BillingTransaction::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['created_at', 'amount_minor', 'currency', 'status'])
                ->map(fn ($t) => [
                    'date' => $t->created_at?->toIso8601String(),
                    'amount_minor' => $t->amount_minor,
                    'currency' => $t->currency,
                    'status' => $t->status,
                ]),
        ]);
    }

    /** TASK-P24-020 — cancel renewal (disable provider subscription). */
    public function cancel(Request $request): JsonResponse
    {
        $row = BillingSubscription::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('state', ['active', 'past_due'])
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return response()->json(['error' => 'No active subscription to cancel.'], 404);
        }

        try {
            $this->midtrans->setSubscriptionEnabled((string) $row->provider_subscription_id, false);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'Gateway error during cancellation.', 'code' => 'GATEWAY_ERROR'], 502);
        }

        $row->state = 'canceled';
        $row->save();
        $this->downgradeToFree($row->user_id);

        return response()->json(['status' => 'canceled', 'plan_code' => $row->plan_code]);
    }

    /** TASK-P24-020 — resume a canceled subscription. */
    public function resume(Request $request): JsonResponse
    {
        $row = BillingSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('state', 'canceled')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return response()->json(['error' => 'No canceled subscription to resume.'], 404);
        }

        try {
            $this->midtrans->setSubscriptionEnabled((string) $row->provider_subscription_id, true);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'Gateway error during resume.', 'code' => 'GATEWAY_ERROR'], 502);
        }

        $row->state = 'active';
        $row->save();

        // Restore entitlement.
        $saas = SaasSubscription::query()->where('user_id', $row->user_id)->first();
        if ($saas !== null) {
            $saas->plan_code = $row->plan_code;
            $saas->state = 'active';
            $saas->save();
        }

        return response()->json(['status' => 'resumed', 'plan_code' => $row->plan_code]);
    }

    /** TASK-P24-019 — downgrade expired/canceled users to free tier safely. */
    private function downgradeToFree(int $userId): void
    {
        $saas = SaasSubscription::query()->where('user_id', $userId)->first();
        if ($saas !== null) {
            $saas->plan_code = 'free';
            $saas->state = 'active';
            $saas->save();
        }
    }

    /** TASK-P24-013..015 — Midtrans notification endpoint (machine-to-machine). */
    public function midtransWebhook(Request $request): JsonResponse
    {
        if (! config('billing.midtrans.webhook_verify')) {
            return response()->json(['error' => 'Webhook verification disabled.'], 503);
        }

        $payload = (string) $request->getContent();
        if (strlen($payload) > 64 * 1024) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        try {
            $event = $this->midtrans->verifyAndNormalizeWebhook($payload, $request->headers->all());
        } catch (RuntimeException $e) {
            // Invalid signature / malformed / unknown event → reject + audit trail via logs only.
            return response()->json(['error' => $e->getMessage()], 403);
        }

        try {
            $outcome = $this->billing->applyEvent($event, json_decode($payload, true) ?? []);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Processing failed; event retained for retry.'], 500);
        }

        return response()->json(['status' => $outcome]);
    }
}
