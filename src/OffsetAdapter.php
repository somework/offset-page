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

use SomeWork\OffsetPage\Exception\InvalidPaginationArgumentException;
use SomeWork\OffsetPage\Logic\AlreadyGetNeededCountException;
use SomeWork\OffsetPage\Logic\Offset;

/**
 * Offset-based pagination adapter for page-based data sources.
 *
 * This adapter converts offset-based pagination requests (like "give me items 50-99")
 * into page-based requests that your data source can understand.
 *
 * @template T
 */
readonly class OffsetAdapter
{
    /**
     * Initialize the adapter with the given data source.
     *
     * Stores the provided SourceInterface implementation for fetching page-based data.
     *
     * @param SourceInterface<T> $source The underlying page-based data source.
     */
    public function __construct(protected SourceInterface $source)
    {
    }

    /**
     * Create an adapter backed by a callback-based source.
     *
     * The callback is called with ($page, $pageSize) and must return a Generator yielding items of type `T`.
     *
     * @param callable(int, int): \Generator<T> $callback Callback that provides page data.
     *
     * @return self<T> An adapter instance that uses the provided callback as its data source.
     */
    public static function fromCallback(callable $callback): self
    {
        return new self(new SourceCallbackAdapter($callback));
    }

    /**
     * Execute an offset-based pagination request and return a result wrapper.
     *
     * @param int $offset   Starting position (0-based).
     * @param int $limit    Maximum number of items to return; zero means no limit.
     * @param int $nowCount Current count of items already fetched (used for progress tracking across requests).
     *
     * @throws InvalidPaginationArgumentException If any argument is invalid (negative values or zero limit with non-zero offset/nowCount).
     * @throws \Throwable                         For errors raised by the underlying source during data retrieval.
     *
     * @return OffsetResult<T> A wrapper exposing the paginated items (via generator() and fetchAll()) respecting the provided offset and limit.
     */
    public function execute(int $offset, int $limit, int $nowCount = 0): OffsetResult
    {
        $this->assertArgumentsAreValid($offset, $limit, $nowCount);

        if (0 === $offset && 0 === $limit && 0 === $nowCount) {
            /** @var OffsetResult<never-return> $result */
            $result = OffsetResult::empty();

            return $result;
        }

        return new OffsetResult($this->logic($offset, $limit, $nowCount));
    }

    /**
     * Return a generator that yields paginated results for the given offset and limit.
     *
     * @param int $offset   The zero-based offset of the first item to return.
     * @param int $limit    The maximum number of items to return; use 0 for no limit.
     * @param int $nowCount The number of items already delivered prior to this call (affects internal page calculation).
     *
     * @throws \Throwable Propagates errors thrown by the underlying source.
     *
     * @return \Generator<T> A generator that yields the resulting items.
     */
    public function generator(int $offset, int $limit, int $nowCount = 0): \Generator
    {
        return $this->execute($offset, $limit, $nowCount)->generator();
    }

    /**
     * Fetches all items for the given offset and limit and returns them as an array.
     *
     * @param int $offset   The zero-based offset at which to start retrieving items.
     * @param int $limit    The maximum number of items to retrieve (0 means no limit).
     * @param int $nowCount The number of items already delivered before this call; affects pagination calculation.
     *
     * @return array<T> The list of items retrieved for the requested offset and limit.
     */
    public function fetchAll(int $offset, int $limit, int $nowCount = 0): array
    {
        return $this->execute($offset, $limit, $nowCount)->fetchAll();
    }

    /**
     * Produces a sequence of per-page generators that provide items according to the offset/limit pagination request.
     *
     * The returned generator yields generators (one per fetched page) that each produce items of type `T`. Pagination continues
     * until the overall requested `limit` is satisfied, the underlying source signals completion, or the computed page/page size is non-positive.
     *
     * @param int $offset   Number of items to skip before starting to collect results.
     * @param int $limit    Maximum number of items to return (0 means no limit).
     * @param int $nowCount Current count of already-delivered items to consider when computing subsequent pages.
     *
     * @throws \Throwable Propagates unexpected errors from the underlying source or pagination logic.
     *
     * @return \Generator<\Generator<T>> A generator that yields per-page generators of items.
     */
    protected function logic(int $offset, int $limit, int $nowCount): \Generator
    {
        $totalDelivered = 0;
        $currentNowCount = $nowCount;

        try {
            while ($this->shouldContinuePagination($limit, $totalDelivered)) {
                $paginationRequest = Offset::logic($offset, $limit, $currentNowCount);

                $page = $paginationRequest->getPage();
                $pageSize = $paginationRequest->getSize();

                if (0 >= $page || 0 >= $pageSize) {
                    return;
                }

                $pageData = $this->source->execute($page, $pageSize);

                if (!$pageData->valid()) {
                    return;
                }

                yield $this->createLimitedGenerator($pageData, $limit, $totalDelivered, $currentNowCount);

                if (0 !== $limit && $totalDelivered >= $limit) {
                    return;
                }
            }
        } catch (AlreadyGetNeededCountException) {
            return;
        }
    }

    /**
     * Validate pagination arguments and throw when they are invalid.
     *
     * @param int $offset   Starting position in the dataset.
     * @param int $limit    Maximum number of items to return (0 means no limit).
     * @param int $nowCount Number of items already fetched prior to this request.
     *
     * @throws InvalidPaginationArgumentException If any parameter is negative, or if `$limit` is 0 while `$offset` or `$nowCount` is non‑zero.
     */
    private function assertArgumentsAreValid(int $offset, int $limit, int $nowCount): void
    {
        foreach ([['offset', $offset], ['limit', $limit], ['nowCount', $nowCount]] as [$name, $value]) {
            if (0 > $value) {
                $description = match ($name) {
                    'offset'   => 'starting position in the dataset',
                    'limit'    => 'maximum number of items to return',
                    'nowCount' => 'number of items already fetched',
                };

                throw InvalidPaginationArgumentException::forInvalidParameter($name, $value, $description);
            }
        }

        if (0 === $limit && (0 !== $offset || 0 !== $nowCount)) {
            throw InvalidPaginationArgumentException::forInvalidZeroLimit($offset, $limit, $nowCount);
        }
    }

    /**
     * Yields items from the provided source generator while enforcing an overall limit.
     *
     * @param \Generator $sourceGenerator  Generator producing source items.
     * @param int        $limit            Overall maximum number of items to yield; 0 means no limit.
     * @param int        &$totalDelivered  Reference to a counter incremented for each yielded item.
     * @param int        &$currentNowCount Reference to the current "now" count incremented for each yielded item.
     *
     * @return \Generator Yields items from `$sourceGenerator` until `$limit` is reached or the source is exhausted; updates `$totalDelivered` and `$currentNowCount`.
     */
    private function createLimitedGenerator(
        \Generator $sourceGenerator,
        int $limit,
        int &$totalDelivered,
        int &$currentNowCount,
    ): \Generator {
        foreach ($sourceGenerator as $item) {
            if (0 !== $limit && $totalDelivered >= $limit) {
                break;
            }

            $totalDelivered++;
            $currentNowCount++;
            yield $item;
        }
    }

    /**
     * Decides whether pagination should continue based on the requested limit and items already delivered.
     *
     * @param int $limit     The overall requested maximum number of items; zero indicates no limit.
     * @param int $delivered The number of items delivered so far.
     *
     * @return bool `true` if pagination should continue (when `$limit` is zero or `$delivered` is less than `$limit`), `false` otherwise.
     */
    private function shouldContinuePagination(int $limit, int $delivered): bool
    {
        return 0 === $limit || $delivered < $limit;
    }
}
