export interface User {
    id: number;
    name: string;
    email: string;
}

export interface Profile {
    user_id: number;
    display_name: string | null;
    locale: string;
    timezone: string;
    week_start_day: string;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
}

export interface LoginPayload {
    email: string;
    password: string;
}

export interface RegisterResponse {
    user: User;
    token: string;
    profile: Profile;
}

export interface LoginResponse {
    user: User;
    token: string;
}

export interface MeResponse {
    user: User;
}

export interface ProfileResponse {
    profile: Profile;
}

export interface UpdateProfilePayload {
    display_name?: string | null;
    locale?: string;
    timezone?: string;
    week_start_day?: string;
}

export const ALLOWED_TIMEZONES = [
    'UTC',
    'Asia/Jakarta',
    'Asia/Makassar',
    'Asia/Jayapura',
    'Asia/Singapore',
    'America/New_York',
    'Europe/London',
] as const;

export const ALLOWED_LOCALES = ['en'] as const;

export const ALLOWED_WEEK_START_DAYS = ['monday', 'sunday', 'saturday'] as const;

export interface ApiError {
    message: string;
    status: number;
    errors?: Record<string, string[]>;
}
