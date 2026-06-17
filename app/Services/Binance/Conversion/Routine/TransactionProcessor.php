<?php

declare(strict_types=1);

namespace App\Services\Binance\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Binance\Authentication\SecretManager;
use App\Services\Binance\Request\GetTradesRequest;
use App\Services\Shared\Configuration\Configuration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class TransactionProcessor
{
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private Configuration $configuration;
    private ImportJob $importJob;
    private ImportJobRepository $repository;
    private ?Carbon $notBefore = null;
    private ?Carbon $notAfter  = null;

    public function setImportJob(ImportJob $importJob): void
    {
        $importJob->refreshInstanceIdentifier();
        $this->repository    = new ImportJobRepository();
        $this->importJob     = $importJob;
        $this->configuration = $importJob->getConfiguration();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    /**
     * @throws ImporterErrorException
     */
    public function download(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        $this->notBefore = '' !== $this->configuration->getDateNotBefore()
            ? new Carbon($this->configuration->getDateNotBefore())
            : null;
        $this->notAfter  = '' !== $this->configuration->getDateNotAfter()
            ? new Carbon($this->configuration->getDateNotAfter())
            : null;

        $apiKey    = SecretManager::getApiKey();
        $apiSecret = SecretManager::getApiSecret();
        $url       = config('binance.url');

        if ('' === $apiKey || '' === $apiSecret) {
            throw new ImporterErrorException('Binance API key or secret is not configured.');
        }

        $symbols = $this->configuration->getBinanceSymbols();
        if (0 === count($symbols)) {
            throw new ImporterErrorException('No Binance symbols configured. Please add symbols in the connect step.');
        }

        $return = [];
        $total  = count($symbols);
        Log::debug(sprintf('Going to download trades for %d symbol(s).', $total));

        foreach ($symbols as $index => $symbol) {
            $symbol = strtoupper(trim($symbol));
            Log::debug(sprintf('[%d/%d] Downloading trades for symbol "%s"', $index + 1, $total, $symbol));

            $request = new GetTradesRequest(
                $url,
                $apiKey,
                $apiSecret,
                $symbol,
                $this->configuration->getDateNotBefore(),
                $this->configuration->getDateNotAfter()
            );
            $request->setTimeOut(config('importer.connection.timeout'));

            try {
                $response         = $request->get();
                $return[$symbol]  = $this->filterTrades($response, $symbol);
                Log::debug(sprintf('Downloaded %d trade(s) for "%s" (after filter: %d)', count($response), $symbol, count($return[$symbol])));
            } catch (ImporterHttpException|ImporterErrorException $e) {
                Log::error(sprintf('Could not download trades for symbol "%s": %s', $symbol, $e->getMessage()));
                $this->importJob->conversionStatus->addWarning(0, sprintf('Could not download trades for %s: %s', $symbol, $e->getMessage()));
                $return[$symbol] = [];
            }
        }

        return $return;
    }

    private function filterTrades(iterable $trades, string $symbol): array
    {
        $return = [];
        foreach ($trades as $trade) {
            $madeOn = $trade->time;

            if ($this->notBefore instanceof Carbon && $madeOn->lt($this->notBefore)) {
                Log::debug(sprintf('Skip trade "%s" #%d because "%s" is before "%s".', $symbol, $trade->id, $madeOn->format(self::DATE_TIME_FORMAT), $this->notBefore->format(self::DATE_TIME_FORMAT)));

                continue;
            }
            if ($this->notAfter instanceof Carbon && $madeOn->gt($this->notAfter)) {
                Log::debug(sprintf('Skip trade "%s" #%d because "%s" is after "%s".', $symbol, $trade->id, $madeOn->format(self::DATE_TIME_FORMAT), $this->notAfter->format(self::DATE_TIME_FORMAT)));

                continue;
            }

            $return[] = $trade;
        }

        return $return;
    }
}
