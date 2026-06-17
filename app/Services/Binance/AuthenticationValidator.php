<?php

declare(strict_types=1);

namespace App\Services\Binance;

use App\Services\Binance\Authentication\SecretManager;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
use Illuminate\Support\Facades\Log;

final class AuthenticationValidator implements AuthenticationValidatorInterface
{
    public function validate(): AuthenticationStatus
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $apiKey    = SecretManager::getApiKey();
        $apiSecret = SecretManager::getApiSecret();

        if ('' === $apiKey || '' === $apiSecret) {
            return AuthenticationStatus::NODATA;
        }

        return AuthenticationStatus::AUTHENTICATED;
    }

    public function getData(): array
    {
        return [
            'api_key'    => SecretManager::getApiKey(),
            'api_secret' => SecretManager::getApiSecret(),
        ];
    }

    public function setData(array $data): void
    {
        SecretManager::saveApiKey($data['api_key'] ?? '');
        SecretManager::saveApiSecret($data['api_secret'] ?? '');
    }
}
