<?php

declare(strict_types=1);

namespace App\Services\Binance\Conversion\Routine;

use App\Models\ImportJob;
use App\Services\Binance\Model\Trade;
use App\Services\Shared\Configuration\Configuration;
use App\Support\Http\CollectsAccounts;
use App\Support\Internal\DuplicateSafetyCatch;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;

final class GenerateTransactions
{
    use CollectsAccounts;
    use DuplicateSafetyCatch;

    private Configuration $configuration;
    private ImportJob $importJob;
    private array $targetAccounts = [];
    private array $userAccounts   = [];
    private int $defaultAccountId = 0;

    public function __construct()
    {
        bcscale(12);
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob     = $importJob;
        $this->configuration = $importJob->getConfiguration();
        $this->defaultAccountId = $this->configuration->getDefaultAccount();
    }

    /**
     * @throws ApiHttpException
     */
    public function collectTargetAccounts(): void
    {
        Log::debug('Binance: collecting target accounts from Firefly III.');
        $array = $this->collectAllTargetAccounts();
        foreach ($array as $number => $info) {
            $this->targetAccounts[$number] = $info['id'];
            $this->userAccounts[$number]   = $info;
        }
        Log::debug(sprintf('Binance: Collected %d target accounts.', count($this->targetAccounts)));
    }

    public function getUserAccounts(): array
    {
        return $this->userAccounts;
    }

    public function getTransactions(array $downloadedBySymbol): array
    {
        $return = [];
        foreach ($downloadedBySymbol as $symbol => $trades) {
            $total = count($trades);
            Log::debug(sprintf('Generating FF3 transactions for symbol "%s" (%d trade(s)).', $symbol, $total));
            foreach ($trades as $index => $trade) {
                Log::debug(sprintf('[%d/%d] Generating transaction for trade #%d', $index + 1, $total, $trade->id));
                $return[] = $this->generateTransaction($trade);
            }
        }

        return $return;
    }

    private function generateTransaction(Trade $trade): array
    {
        ['base' => $base, 'quote' => $quote] = Trade::extractCurrencies($trade->symbol);

        $return      = [
            'apply_rules'             => $this->configuration->isRules(),
            'fire_webhooks'           => $this->configuration->isWebhooks(),
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];

        $transaction = [
            'date'          => $trade->time->toW3cString(),
            'amount'        => $trade->quoteQty,
            'currency_code' => $quote,
            'description'   => $trade->getDescription(),
            'notes'         => $trade->getNotes(),
            'external_id'   => $trade->getExternalId(),
            'tags'          => ['binance'],
            'order'         => 0,
        ];

        if ($trade->isBuyer) {
            // User buys base (BTC) with quote (EUR): withdrawal from FF3 account
            $transaction['type']             = 'withdrawal';
            $transaction['source_id']        = $this->resolveAccountId();
            $transaction['destination_name'] = 'Binance';
        } else {
            // User sells base (BTC) for quote (EUR): deposit into FF3 account
            $transaction['type']             = 'deposit';
            $transaction['destination_id']   = $this->resolveAccountId();
            $transaction['source_name']      = 'Binance';
        }

        $return['transactions'][] = $transaction;
        Log::debug(sprintf('Generated FF3 transaction for trade #%d (%s %s)', $trade->id, $trade->isBuyer ? 'buy' : 'sell', $trade->symbol));

        return $return;
    }

    private function resolveAccountId(): int
    {
        $accounts = $this->configuration->getAccounts();

        // accounts may map symbol → ff3_account_id (set by ConnectController)
        // if none set, fall back to default_account
        foreach ($accounts as $accountId) {
            if ($accountId > 0) {
                return (int) $accountId;
            }
        }

        return $this->defaultAccountId > 0 ? $this->defaultAccountId : 1;
    }
}
