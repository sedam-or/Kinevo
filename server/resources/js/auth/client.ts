import type { ApiError, LoginPayload, LoginResponse, MeResponse, ProfileResponse, RegisterPayload, RegisterResponse, UpdateProfilePayload } from './types';

const TOKEN_KEY = 'kinevo.auth.token';

export function readToken(): string | null {
    if (typeof localStorage === 'undefined') {
        return null;
    }
    return localStorage.getItem(TOKEN_KEY);
}

export function writeToken(token: string | null): void {
    if (typeof localStorage === 'undefined') {
        return;
    }
    if (token === null) {
        localStorage.removeItem(TOKEN_KEY);
    } else {
        localStorage.setItem(TOKEN_KEY, token);
    }
}

export function clearToken(): void {
    writeToken(null);
}

async function parseError(response: Response): Promise<ApiError> {
    let message = `Request failed (${response.status})`;
    let errors: Record<string, string[]> | undefined;

    try {
        const body = (await response.json()) as Record<string, unknown>;
        if (typeof body.error === 'string') {
            message = body.error;
        }
        if (body.errors && typeof body.errors === 'object') {
            errors = body.errors as Record<string, string[]>;
        }
    } catch {
        // non-JSON error body
    }

    return { message, status: response.status, errors };
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
    const token = readToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...((init?.headers as Record<string, string>) ?? {}),
    };

    const response = await fetch(`/api/v1${path}`, { ...init, headers });

    if (!response.ok) {
        throw await parseError(response);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

export const authApi = {
    register(payload: RegisterPayload): Promise<RegisterResponse> {
        return request<RegisterResponse>('/auth/register', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    login(payload: LoginPayload): Promise<LoginResponse> {
        return request<LoginResponse>('/auth/login', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    logout(): Promise<{ message: string }> {
        return request<{ message: string }>('/auth/logout', { method: 'POST' });
    },

    me(): Promise<MeResponse> {
        return request<MeResponse>('/auth/me');
    },

    profile(): Promise<ProfileResponse> {
        return request<ProfileResponse>('/profile');
    },

    updateProfile(payload: UpdateProfilePayload): Promise<ProfileResponse> {
        return request<ProfileResponse>('/profile', {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },
};
