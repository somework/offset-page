<?php

declare(strict_types=1);

/*
 * This file is part of the SomeWork/OffsetPage package.
 *
 * (c) Pinchuk Igor <i.pinchuk.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SomeWork\OffsetPage;

/**
 * Result of an offset-based pagination request.
 *
 * Provides multiple ways to access the paginated data:
 * - fetchAll(): Get all results as an array
 * - fetch(): Iterate through results one by one
 * - generator(): Get a generator for advanced use cases
 * - getFetchedCount(): Get the number of items returned
 *
 * @template T
 */
class OffsetResult
{
    private int $fetchedCount = 0;
    private \Generator $generator;

    /**
     * Create an OffsetResult from a generator that yields page generators.
     *
     * The provided generator must yield per-page generators whose values are items of type T; the constructor stores an internal generator that will iterate items across all pages in sequence. The internal generator can be consumed only once.
     *
     * @param \Generator<\Generator<T>> $sourceResultGenerator Generator that yields per-page generators of items of type T.
     */
    public function __construct(\Generator $sourceResultGenerator)
    {
        $this->generator = $this->execute($sourceResultGenerator);
    }

    /**
     * Create an OffsetResult that yields no items.
     *
     * @return OffsetResult<T> An OffsetResult containing zero elements.
     */
    public static function empty(): self
    {
        /** @return \Generator<\Generator<never-return>> */
        $emptyGenerator = static fn () => yield from [];

        return new self($emptyGenerator());
    }

    /**
     * Retrieve the next item from the internal generator.
     *
     * The internal generator is advanced so subsequent calls return the following items.
     *
     * @return T|null The next yielded value, or `null` if there are no more items.
     */
    public function fetch(): mixed
    {
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();

            return $value;
        }

        return null; // End of data
    }

    /**
     * Retrieve all remaining items from the internal generator as an array.
     *
     * Consuming the returned items advances the internal generator until it is exhausted.
     *
     * @return array<T> An array containing every remaining yielded item; empty if none remain.
     */
    public function fetchAll(): array
    {
        $result = [];
        while ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Get the internal generator used to stream paginated items.
     *
     * The returned generator can be consumed only once; calling fetch(), fetchAll(), or iterating the generator will exhaust it.
     *
     * @return \Generator<T> The internal generator that yields items of type T.
     */
    public function generator(): \Generator
    {
        return $this->generator;
    }

    /**
     * Number of items fetched so far.
     *
     * @return int The count of items that have been retrieved from the internal generator.
     */
    public function getFetchedCount(): int
    {
        return $this->fetchedCount;
    }

    /**
     * Flatten a generator of page generators and yield each item in sequence.
     *
     * Increments the instance's fetched count for every yielded item.
     *
     * @param \Generator<\Generator<T>> $generator Generator that yields page generators; each page generator yields items of type T.
     *
     * @return \Generator<T> Generator that yields items of type T from all pages in order.
     */
    protected function execute(\Generator $generator): \Generator
    {
        foreach ($generator as $pageGenerator) {
            foreach ($pageGenerator as $result) {
                $this->fetchedCount++;
                yield $result;
            }
        }
    }
}
