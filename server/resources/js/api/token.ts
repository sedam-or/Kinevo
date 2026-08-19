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
