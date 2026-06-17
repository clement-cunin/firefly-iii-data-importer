<?php

declare(strict_types=1);

namespace App\Services\Binance\Request;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Binance\Response\GetTradesResponse;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

final class GetTradesRequest extends Request
{
    public function __construct(
        string $url,
        #[SensitiveParameter] string $apiKey,
        #[SensitiveParameter] string $apiSecret,
        private readonly string $symbol,
        string $startTime = '',
        string $endTime = ''
    ) {
        $this->setBase($url);
        $this->setApiKey($apiKey);
        $this->setApiSecret($apiSecret);
        $this->setUrl('api/v3/myTrades');

        $params = ['symbol' => strtoupper($this->symbol), 'limit' => 1000];
        if ('' !== $startTime) {
            // convert Y-m-d to ms timestamp
            $params['startTime'] = strtotime($startTime) * 1000;
        }
        if ('' !== $endTime) {
            $params['endTime'] = (strtotime($endTime) + 86399) * 1000; // end of day
        }
        $this->setParameters($params);
    }

    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    public function get(): GetTradesResponse
    {
        Log::debug(sprintf('Binance GetTradesRequest::get() for symbol "%s"', $this->symbol));
        $data = $this->signedGet();

        if (!is_array($data)) {
            return new GetTradesResponse([]);
        }

        $response = new GetTradesResponse($data);
        $response->processData();

        return $response;
    }
}
