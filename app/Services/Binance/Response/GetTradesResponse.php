<?php

declare(strict_types=1);

namespace App\Services\Binance\Response;

use App\Services\Binance\Model\Trade;
use Countable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Iterator;

final class GetTradesResponse implements Iterator, Countable
{
    private readonly Collection $collection;
    private int $position = 0;

    public function __construct(private readonly array $data)
    {
        $this->collection = new Collection();
    }

    public function processData(): void
    {
        Log::debug(sprintf('Processing %d Binance trades from response.', count($this->data)));
        foreach ($this->data as $array) {
            $this->collection->push(Trade::fromArray($array));
        }
    }

    public function count(): int
    {
        return $this->collection->count();
    }

    public function current(): Trade
    {
        return $this->collection->get($this->position);
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return $this->collection->has($this->position);
    }
}
