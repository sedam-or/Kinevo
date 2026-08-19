export type ApiErrorCode =
    | 'NETWORK'
    | 'OFFLINE'
    | 'UNAUTHORIZED'
    | 'FORBIDDEN'
    | 'NOT_FOUND'
    | 'CONFLICT'
    | 'VALIDATION'
    | 'TOO_MANY_REQUESTS'
    | 'UNAVAILABLE'
    | 'SERVER'
    | 'UNKNOWN';

export const HTTP_STATUS_TO_CODE: Record<number, ApiErrorCode> = {
    401: 'UNAUTHORIZED',
    403: 'FORBIDDEN',
    404: 'NOT_FOUND',
    409: 'CONFLICT',
    422: 'VALIDATION',
    429: 'TOO_MANY_REQUESTS',
    503: 'UNAVAILABLE',
};

export interface ApiError {
    code: ApiErrorCode;
    status: number;
    message: string;
    /** Server-side field validation errors keyed by field name. */
    errors?: Record<string, string[]>;
    /** Stable server-provided error code (e.g. SCHEDULE_VERSION_CONFLICT). */
    serverCode?: string;
    /** True when the failure was caused by a network/offline condition. */
    retryable: boolean;
}

export function codeForStatus(status: number): ApiErrorCode {
    return HTTP_STATUS_TO_CODE[status] ?? (status >= 500 ? 'SERVER' : 'UNKNOWN');
}
