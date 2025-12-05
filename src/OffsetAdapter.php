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
 * @template T
 */
class OffsetAdapter
{
    /**
     * @param SourceInterface<T> $source
     */
    public function __construct(protected readonly SourceInterface $source)
    {
    }

    /**
     * Execute pagination request with offset and limit.
     *
     * @param int $offset   Starting position (0-based)
     * @param int $limit    Maximum number of items to return
     * @param int $nowCount Current count of items already fetched (used for progress tracking in multi-request scenarios)
     *
     * @return OffsetResult<T>
     */
    public function execute(int $offset, int $limit, int $nowCount = 0): OffsetResult
    {
        $this->assertArgumentsAreValid($offset, $limit, $nowCount);

        if (0 === $offset && 0 === $limit && 0 === $nowCount) {
            /** @return \Generator<SourceResultInterface<T>> */
            $emptyGenerator = function () {
                // Empty generator for zero-limit sentinel - yields nothing
                yield from [];
            };
            return new OffsetResult($emptyGenerator());
        }

        return new OffsetResult($this->logic($offset, $limit, $nowCount));
    }

    /**
     * @return \Generator<SourceResultInterface<T>>
     */
    protected function logic(int $offset, int $limit, int $nowCount): \Generator
    {
        $delivered = 0;
        $progressNowCount = $nowCount;

        try {
            while (0 === $limit || $delivered < $limit) {
                $offsetResult = Offset::logic($offset, $limit, $progressNowCount);

                $page = $offsetResult->getPage();
                $size = $offsetResult->getSize();

                if (0 >= $size) {
                    return;
                }

                $generator = $this->source->execute($page, $size)->generator();

                if (!$generator->valid()) {
                    return;
                }

                yield new SourceResultCallbackAdapter(
                    function () use ($generator, &$delivered, &$progressNowCount, $limit) {
                        foreach ($generator as $item) {
                            if (0 !== $limit && $delivered >= $limit) {
                                break;
                            }

                            $delivered++;
                            $progressNowCount++;
                            yield $item;
                        }
                    },
                );

                if (0 !== $limit && $delivered >= $limit) {
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
}
