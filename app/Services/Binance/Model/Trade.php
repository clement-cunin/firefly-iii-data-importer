<?php

declare(strict_types=1);

namespace App\Services\Binance\Model;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use JsonException;
use Ramsey\Uuid\Uuid;

final class Trade
{
    public string $symbol;
    public int    $id;
    public int    $orderId;
    public string $price;
    public string $qty;
    public string $quoteQty;
    public string $commission;
    public string $commissionAsset;
    public Carbon $time;
    public bool   $isBuyer;
    public bool   $isMaker;

    public static function fromArray(array $array): self
    {
        Log::debug('Binance Trade::fromArray', $array);
        $object                  = new self();
        $object->symbol          = (string) ($array['symbol'] ?? '');
        $object->id              = (int) ($array['id'] ?? 0);
        $object->orderId         = (int) ($array['orderId'] ?? 0);
        $object->price           = (string) ($array['price'] ?? '0');
        $object->qty             = (string) ($array['qty'] ?? '0');
        $object->quoteQty        = (string) ($array['quoteQty'] ?? '0');
        $object->commission      = (string) ($array['commission'] ?? '0');
        $object->commissionAsset = (string) ($array['commissionAsset'] ?? '');
        $object->isBuyer         = (bool) ($array['isBuyer'] ?? false);
        $object->isMaker         = (bool) ($array['isMaker'] ?? false);

        $timestamp   = (int) ($array['time'] ?? 0);
        $object->time = Carbon::createFromTimestampMs($timestamp, config('app.timezone'));

        return $object;
    }

    public function getExternalId(): string
    {
        return sprintf('binance-%s-%d', $this->symbol, $this->id);
    }

    public function getDescription(): string
    {
        ['base' => $base] = self::extractCurrencies($this->symbol);
        $side              = $this->isBuyer ? 'Buy' : 'Sell';

        return sprintf('%s %s %s on Binance', $side, $this->qty, $base);
    }

    public function getNotes(): string
    {
        ['base' => $base, 'quote' => $quote] = self::extractCurrencies($this->symbol);
        $lines                               = [
            sprintf('Symbol: %s', $this->symbol),
            sprintf('Price: %s %s/%s', $this->price, $quote, $base),
            sprintf('Quantity: %s %s', $this->qty, $base),
            sprintf('Quote quantity: %s %s', $this->quoteQty, $quote),
        ];
        if ('0' !== $this->commission && '' !== $this->commission) {
            $lines[] = sprintf('Fee: %s %s', $this->commission, $this->commissionAsset);
        }
        $lines[] = sprintf('Order ID: %d', $this->orderId);
        $lines[] = sprintf('Trade ID: %d', $this->id);

        return implode("\n", $lines);
    }

    public static function extractCurrencies(string $symbol): array
    {
        // Ordered by length (longest first) to avoid mismatches like USDT vs USD
        $knownQuotes = ['USDT', 'BUSD', 'USDC', 'TUSD', 'BIDR', 'DAI', 'EUR', 'GBP', 'BRL', 'USD', 'BTC', 'ETH', 'BNB', 'XRP', 'ADA', 'TRX'];
        foreach ($knownQuotes as $quote) {
            if (str_ends_with($symbol, $quote)) {
                return [
                    'base'  => substr($symbol, 0, -strlen($quote)),
                    'quote' => $quote,
                ];
            }
        }

        // Fallback: last 3 chars as quote
        return [
            'base'  => substr($symbol, 0, -3),
            'quote' => substr($symbol, -3),
        ];
    }
}
