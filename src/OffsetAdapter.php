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
     * Create an adapter with a custom source implementation.
     *
     * @param SourceInterface<T> $source
     */
    public function __construct(protected SourceInterface $source)
    {
    }

    /**
     * Create an adapter using a callback function.
     *
     * This is the most convenient way to use the adapter for simple cases.
     * Your callback will receive (page, pageSize) and should return a Generator.
     *
     * @param callable(int, int): \Generator<T> $callback
     *
     * @return self<T>
     */
    public static function fromCallback(callable $callback): self
    {
        return new self(new SourceCallbackAdapter($callback));
    }

    /**
     * Execute pagination request with offset and limit.
     *
     * @param int $offset Starting position (0-based)
     * @param int $limit Maximum number of items to return
     * @param int $nowCount Current count of items already fetched (used for progress tracking in multi-request scenarios)
     *
     * @return OffsetResult<T>
     *
     * @throws \Throwable
     */
    public function execute(int $offset, int $limit, int $nowCount = 0): OffsetResult
    {
        $this->assertArgumentsAreValid($offset, $limit, $nowCount);

        if (0 === $offset && 0 === $limit && 0 === $nowCount) {
            /** @var  OffsetResult<never-return> $result */
            $result = OffsetResult::empty();

            return $result;
        }

        return new OffsetResult($this->logic($offset, $limit, $nowCount));
    }

    /**
     * Get results as a generator (advanced usage).
     *
     * For most use cases, use execute() instead and call fetchAll() on the result.
     *
     * @param int $offset
     * @param int $limit
     * @param int $nowCount
     *
     * @return \Generator<T>
     *
     * @throws \Throwable
     */
    public function generator(int $offset, int $limit, int $nowCount = 0): \Generator
    {
        return $this->execute($offset, $limit, $nowCount)->generator();
    }

    /**
     * Execute pagination and return all results as an array.
     *
     * This is a convenience method for the most common use case.
     *
     * @param int $offset
     * @param int $limit
     * @param int $nowCount
     *
     * @return array<T>
     *
     * @throws \Throwable
     */
    public function fetchAll(int $offset, int $limit, int $nowCount = 0): array
    {
        return $this->execute($offset, $limit, $nowCount)->fetchAll();
    }

    /**
     * @return \Generator<\Generator<T>>
     *
     * @throws \Throwable
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

    private function assertArgumentsAreValid(int $offset, int $limit, int $nowCount): void
    {
        foreach ([['offset', $offset], ['limit', $limit], ['nowCount', $nowCount]] as [$name, $value]) {
            if (0 > $value) {
                $description = match ($name) {
                    'offset' => 'starting position in the dataset',
                    'limit' => 'maximum number of items to return',
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
     * Create a generator that respects the overall limit.
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
     * Determine if pagination should continue.
     */
    private function shouldContinuePagination(int $limit, int $delivered): bool
    {
        return 0 === $limit || $delivered < $limit;
    }
}
