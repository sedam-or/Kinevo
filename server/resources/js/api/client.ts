import { clearToken, readToken } from './token';
import { codeForStatus, type ApiError, type ApiErrorCode } from './types';

export interface ApiClientOptions {
    baseUrl?: string;
    onUnauthorized?: () => void;
    maxRetries?: number;
    retryDelayMs?: number;
    retryPolicy?: RetryPolicy;
    /** Tracks connectivity state for offline detection. */
    isOnline?: () => boolean;
}

export interface ClientRequestInit extends RequestInit {
    /** Skip automatic retry for this request. */
    noRetry?: boolean;
}

export interface RetryPolicy {
    shouldRetry(status: number): boolean;
    retryDelayMs(attempt: number): number;
}

export const defaultRetryPolicy: RetryPolicy = {
    shouldRetry(status: number): boolean {
        // Retry transient/network failures and server 5xx; never retry 4xx.
        return status === 0 || status === 429 || status >= 500;
    },
    retryDelayMs(attempt: number): number {
        // Linear backoff: 200ms, 400ms, 600ms...
        return 200 * attempt;
    },
};

export function isNetworkFailure(error: unknown): boolean {
    return error instanceof TypeError && /fetch|network|failed to fetch/i.test(error.message);
}

function networkError(): ApiError {
    return {
        code: 'NETWORK',
        status: 0,
        message: 'Could not reach the server. Check your connection and try again.',
        retryable: true,
    };
}

function offlineError(): ApiError {
    return {
        code: 'OFFLINE',
        status: 0,
        message: 'You are offline. Changes are saved locally and will sync when the connection returns.',
        retryable: true,
    };
}

export class ApiErrorClass extends Error implements ApiError {
    readonly code: ApiErrorCode;
    readonly status: number;
    readonly errors?: Record<string, string[]>;
    readonly serverCode?: string;
    readonly retryable: boolean;

    constructor(error: ApiError) {
        super(error.message);
        this.name = 'ApiError';
        this.code = error.code;
        this.status = error.status;
        this.errors = error.errors;
        this.serverCode = error.serverCode;
        this.retryable = error.retryable;
    }
}

async function parseError(response: Response): Promise<ApiError> {
    const status = response.status;
    const code = codeForStatus(status);

    let message = `Request failed (${status})`;
    let errors: Record<string, string[]> | undefined;
    let serverCode: string | undefined;

    try {
        const body = (await response.json()) as Record<string, unknown>;
        if (typeof body.error === 'string') {
            message = body.error;
        }
        if (typeof body.code === 'string') {
            serverCode = body.code;
        }
        if (serverCode === 'ENTITLEMENT_LIMIT') {
            // TASK-P23-008 — surface upgrade context for the UI.
            (body as { plan?: unknown; entitlement?: unknown });
        }
        if (body.errors && typeof body.errors === 'object') {
            errors = body.errors as Record<string, string[]>;
        }
    } catch {
        // non-JSON error body
    }

    return {
        code,
        status,
        message,
        errors,
        serverCode,
        retryable: defaultRetryPolicy.shouldRetry(status),
    };
}

export class ApiClient {
    private readonly baseUrl: string;
    private readonly onUnauthorized?: () => void;
    private readonly retryPolicy: RetryPolicy;
    private readonly isOnline: () => boolean;
    private readonly maxRetries: number;

    constructor(options: ApiClientOptions = {}) {
        this.baseUrl = options.baseUrl ?? '/api/v1';
        this.onUnauthorized = options.onUnauthorized;
        this.retryPolicy = options.retryPolicy ?? defaultRetryPolicy;
        this.isOnline = options.isOnline ?? (() => navigator.onLine !== false);
        this.maxRetries = options.maxRetries ?? 2;
    }

    private async attempt<T>(path: string, init: RequestInit): Promise<T> {
        const token = readToken();
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...((init.headers as Record<string, string>) ?? {}),
        };

        const response = await fetch(`${this.baseUrl}${path}`, { ...init, headers });

        if (response.status === 401 && this.onUnauthorized) {
            this.onUnauthorized();
        }

        if (response.ok) {
            if (response.status === 204) {
                return undefined as T;
            }
            return (await response.json()) as T;
        }

        throw new ApiErrorClass(await parseError(response));
    }

    async request<T>(path: string, init: ClientRequestInit = {}): Promise<T> {
        const { noRetry, ...requestInit } = init;

        if (!this.isOnline()) {
            throw new ApiErrorClass(offlineError());
        }

        let lastError: ApiErrorClass | undefined;
        const attempts = this.maxRetries + 1;

        for (let attempt = 1; attempt <= attempts; attempt++) {
            try {
                return await this.attempt<T>(path, requestInit);
            } catch (error) {
                if (isNetworkFailure(error)) {
                    lastError = new ApiErrorClass(networkError());
                } else if (error instanceof ApiErrorClass) {
                    lastError = error;
                } else {
                    lastError = new ApiErrorClass({
                        code: 'UNKNOWN',
                        status: 0,
                        message: error instanceof Error ? error.message : String(error),
                        retryable: false,
                    });
                }

                if (!noRetry && lastError.retryable && attempt < attempts) {
                    await this.delay(this.retryPolicy.retryDelayMs(attempt));
                    continue;
                }

                throw lastError;
            }
        }

        throw lastError;
    }

    private delay(ms: number): Promise<void> {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }
}

export const apiClient = new ApiClient({
    onUnauthorized: () => {
        clearToken();
    },
});
