<?php

declare(strict_types=1);

namespace App\Http\Controllers\Import\Binance;

use App\Http\Controllers\Controller;
use App\Repository\ImportJob\ImportJobRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ConnectController extends Controller
{
    private ImportJobRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportJobRepository();
    }

    public function index(string $identifier): View
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $symbols       = implode(',', $configuration->getBinanceSymbols());

        return view('import.010-binance.index', compact('identifier', 'symbols'));
    }

    public function postIndex(Request $request, string $identifier): RedirectResponse|View
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();

        $symbolsRaw = (string) $request->input('symbols', '');
        $symbols    = array_values(array_filter(array_map('strtoupper', array_map('trim', explode(',', $symbolsRaw)))));

        if (0 === count($symbols)) {
            $symbols = implode(',', $configuration->getBinanceSymbols());

            return view('import.010-binance.index', [
                'identifier' => $identifier,
                'symbols'    => $symbols,
                'error'      => 'Please enter at least one trading pair (e.g. BTCEUR).',
            ]);
        }

        $configuration->setBinanceSymbols($symbols);
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        Log::debug(sprintf('Saved %d Binance symbol(s) to config.', count($symbols)));

        return redirect(route('data-conversion.index', [$identifier]));
    }
}
