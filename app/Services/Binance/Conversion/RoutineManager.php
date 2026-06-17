<?php

declare(strict_types=1);

namespace App\Services\Binance\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Binance\Conversion\Routine\GenerateTransactions;
use App\Services\Binance\Conversion\Routine\TransactionProcessor;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

final class RoutineManager implements RoutineManagerInterface
{
    private Configuration $configuration;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;
    private ImportJobRepository $repository;
    private ImportJob $importJob;
    private array $downloaded = [];

    public function __construct(ImportJob $importJob)
    {
        $this->importJob            = $importJob;
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->repository           = new ImportJobRepository();
        $this->importJob->refreshInstanceIdentifier();
        $this->configuration        = $this->importJob->getConfiguration();
        $this->transactionProcessor->setImportJob($this->importJob);
        $this->transactionGenerator->setImportJob($this->importJob);
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return $this->importJob->getServiceAccounts();
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // Step 1: download trades from Binance
        try {
            $this->downloaded = $this->transactionProcessor->download();
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('Could not download from Binance: %s', $e->getMessage()));
            $this->importJob->conversionStatus->addError(0, sprintf('Could not download from Binance: %s', $e->getMessage()));
            $this->repository->saveToDisk($this->importJob);

            throw $e;
        }

        $this->importJob = $this->transactionProcessor->getImportJob();
        $this->repository->saveToDisk($this->importJob);

        // Step 2: check we got something
        $total = array_sum(array_map('count', $this->downloaded));
        if (0 === $total) {
            Log::warning('No Binance trades downloaded.');
            $this->importJob->conversionStatus->addError(0, 'No trades were downloaded from Binance. Check your symbols and date range.');
            $this->repository->saveToDisk($this->importJob);

            return [];
        }
        Log::debug(sprintf('Downloaded %d trade(s) across all symbols.', $total));

        // Step 3: collect Firefly III target accounts
        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->importJob->conversionStatus->addError(0, sprintf('Error collecting target accounts: %s', $e->getMessage()));
            $this->repository->saveToDisk($this->importJob);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        // Step 4: generate FF3 transactions
        $transactions = $this->transactionGenerator->getTransactions($this->downloaded);
        Log::debug(sprintf('Generated %d FF3 transaction(s).', count($transactions)));

        return $transactions;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
