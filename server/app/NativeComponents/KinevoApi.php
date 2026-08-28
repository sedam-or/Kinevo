<?php

namespace App\NativeComponents;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the Kinevo backend OpenAPI (docs/api/openapi.yaml).
 * The mobile app is a presentation front over the SAME monolith — no
 * new endpoints. Token lives in app storage (persisted across boots);
 * native SecureStorage hardening is tracked as a P27 follow-up.
 */
final class KinevoApi
{
    private static ?bool $healthCache = null;

    public static function base(): string
    {
        return rtrim((string) config('native.api_base', 'http://10.0.2.2:8000/api/v1'), '/');
    }

    public static function token(): ?string
    {
        $file = storage_path('app/kinevo_token.txt');
        if (is_file($file) && ($token = trim((string) file_get_contents($file))) !== '') {
            return $token;
        }

        return null;
    }

    public static function authed(): bool
    {
        return self::token() !== null;
    }

    public static function login(string $email, string $password): bool
    {
        $res = Http::acceptJson()
            ->connectTimeout(8)
            ->timeout(20)
            ->post(self::base().'/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $token = $res->json('token') ?? $res->json('data.token');
        if ($res->successful() && is_string($token) && $token !== '') {
            file_put_contents(storage_path('app/kinevo_token.txt'), $token);
            self::$healthCache = null;

            return true;
        }

        return false;
    }

    public static function logout(): void
    {
        @unlink(storage_path('app/kinevo_token.txt'));
        self::$healthCache = null;
    }

    /**
     * Offline indicator backing state. Cached ~15s per process so the
     * shell doesn't block every mount on a health round-trip.
     */
    public static function health(): bool
    {
        $now = time();
        static $last = 0;
        if (self::$healthCache !== null && ($now - $last) < 15) {
            return self::$healthCache;
        }
        $last = $now;
        try {
            self::$healthCache = Http::connectTimeout(4)->timeout(5)->get(self::base().'/health')->successful();
        } catch (\Throwable) {
            self::$healthCache = false;
        }

        return self::$healthCache;
    }

    public static function get(string $uri, array $query = [])
    {
        return Http::withToken(self::token())
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(20)
            ->get(self::base().$uri, $query);
    }

    public static function post(string $uri, array $payload = [])
    {
        return Http::withToken(self::token())
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(20)
            ->post(self::base().$uri, $payload);
    }

    public static function delete(string $uri)
    {
        return Http::withToken(self::token())
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(20)
            ->delete(self::base().$uri);
    }

    public static function operationId(): string
    {
        return Str::uuid()->toString();
    }
}
