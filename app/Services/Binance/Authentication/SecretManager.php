<?php

declare(strict_types=1);

namespace App\Services\Binance\Authentication;

use Illuminate\Support\Facades\Log;
use SensitiveParameter;

final class SecretManager
{
    public const string API_KEY    = 'binance_api_key';
    public const string API_SECRET = 'binance_api_secret';

    public static function getApiKey(): string
    {
        if (!self::hasApiKey()) {
            Log::debug('Binance: No API key in session, will return config variable.');

            return (string) config('binance.api_key');
        }

        return (string) session()->get(self::API_KEY);
    }

    public static function getApiSecret(): string
    {
        if (!self::hasApiSecret()) {
            Log::debug('Binance: No API secret in session, will return config variable.');

            return (string) config('binance.api_secret');
        }

        return (string) session()->get(self::API_SECRET);
    }

    private static function hasApiKey(): bool
    {
        return '' !== (string) session()->get(self::API_KEY);
    }

    private static function hasApiSecret(): bool
    {
        return '' !== (string) session()->get(self::API_SECRET);
    }

    public static function saveApiKey(#[SensitiveParameter] string $apiKey): void
    {
        session()->put(self::API_KEY, $apiKey);
    }

    public static function saveApiSecret(#[SensitiveParameter] string $apiSecret): void
    {
        session()->put(self::API_SECRET, $apiSecret);
    }
}
