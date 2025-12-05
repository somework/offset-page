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

        if ($offset === 0 && $limit === 0 && $nowCount === 0) {
            return new OffsetResult((function () {
                if (false) {
                    yield null; // generator placeholder
                }
            })());
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
            while ($limit === 0 || $delivered < $limit) {
                $offsetResult = Offset::logic($offset, $limit, $progressNowCount);
                if ($offsetResult === null) {
                    return;
                }

                $page = $offsetResult->getPage();
                $size = $offsetResult->getSize();

                if ($size <= 0) {
                    return;
                }

                $generator = $this->source->execute($page, $size)->generator();

                if (!$generator->valid()) {
                    return;
                }

                yield new SourceResultCallbackAdapter(
                    function () use ($generator, &$delivered, &$progressNowCount, $limit) {
                        foreach ($generator as $item) {
                            if ($limit !== 0 && $delivered >= $limit) {
                                break;
                            }

                            $delivered++;
                            $progressNowCount++;
                            yield $item;
                        }
                    },
                );

                if ($limit !== 0 && $delivered >= $limit) {
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
            if ($value < 0) {
                throw new \InvalidArgumentException(sprintf('%s must be greater than or equal to zero.', $name));
            }
        }

        if ($limit === 0 && ($offset !== 0 || $nowCount !== 0)) {
            throw new \InvalidArgumentException('Zero limit is only allowed when offset and nowCount are also zero.');
        }
    }
}
