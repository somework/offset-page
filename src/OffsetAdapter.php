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
        return new OffsetResult($this->logic($offset, $limit, $nowCount));
    }

    /**
     * @return \Generator<SourceResultInterface<T>>
     */
    protected function logic(int $offset, int $limit, int $nowCount): \Generator
    {
        try {
            while ($offsetResult = Offset::logic($offset, $limit, $nowCount)) {
                $generator = $this->source->execute($offsetResult->getPage(), $offsetResult->getSize())->generator();

                if (!$generator->valid()) {
                    return;
                }

                yield new SourceResultCallbackAdapter(
                    function () use ($generator, &$nowCount) {
                        foreach ($generator as $item) {
                            $nowCount++;
                            yield $item;
                        }
                    },
                );
            }
        } catch (AlreadyGetNeededCountException) {
            return;
        }
    }
}
