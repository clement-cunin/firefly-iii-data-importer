<?php

declare(strict_types=1);

namespace App\Services\Binance\Request;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use JsonException;
use SensitiveParameter;

abstract class Request
{
    private string $base;
    private string $url;
    private array  $parameters = [];
    private float  $timeOut    = 31.415;
    private string $apiKey;
    private string $apiSecret;

    abstract public function get(): mixed;

    public function setBase(string $base): void
    {
        $this->base = $base;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    public function setApiKey(#[SensitiveParameter] string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setApiSecret(#[SensitiveParameter] string $apiSecret): void
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function signedGet(): array
    {
        $timestamp              = (int) round(microtime(true) * 1000);
        $params                 = array_merge($this->parameters, ['timestamp' => $timestamp]);
        $queryString            = http_build_query($params);
        $signature              = hash_hmac('sha256', $queryString, $this->apiSecret);
        $queryString           .= '&signature=' . $signature;

        $fullUrl = sprintf('%s/%s?%s', rtrim($this->base, '/'), $this->url, $queryString);
        Log::debug(sprintf('Binance signedGet: %s/%s (params hidden)', rtrim($this->base, '/'), $this->url));

        $client  = new Client(['connect_timeout' => $this->timeOut]);

        try {
            $res = $client->request('GET', $fullUrl, [
                'headers' => [
                    'X-MBX-APIKEY' => $this->apiKey,
                    'Accept'        => 'application/json',
                    'User-Agent'    => sprintf('FF3-data-importer/%s', config('importer.version')),
                ],
            ]);
        } catch (TransferException|GuzzleException $e) {
            $statusCode = $e->getCode();
            if (429 === $statusCode || 418 === $statusCode) {
                Log::warning('Binance rate limit hit, sleeping 60s.');
                sleep(60);

                return [];
            }
            $body = '';
            if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                $body = (string) $e->getResponse()->getBody();
                Log::error(sprintf('Binance HTTP error body: %s', $body));
            }

            throw new ImporterHttpException(sprintf('Binance API error: %s', $e->getMessage()), 0, $e);
        }

        $body = (string) $res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException(sprintf('Binance JSON decode error: %s. Body: %s', $e->getMessage(), $body));
        }

        if (array_key_exists('code', $json) && $json['code'] < 0) {
            throw new ImporterErrorException(sprintf('Binance API returned error %d: %s', $json['code'], $json['msg'] ?? ''));
        }

        return $json;
    }
}
