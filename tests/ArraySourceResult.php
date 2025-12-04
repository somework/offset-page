<?php

/*
 * This file is part of the SomeWork/OffsetPage package.
 *
 * (c) Pinchuk Igor <i.pinchuk.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SomeWork\OffsetPage\Tests;

use SomeWork\OffsetPage\SourceResultInterface;

/**
 * @template T
 *
 * @implements SourceResultInterface<T>
 */
class ArraySourceResult implements SourceResultInterface
{
    /**
     * @param array<T> $data
     */
    public function __construct(protected array $data, protected int $resultsCount)
    {
    }

    /**
     * @return \Generator<T>
     */
    public function generator(): \Generator
    {
        foreach ($this->data as $item) {
            yield $item;
        }
    }

    public function getResultCount(): int
    {
        return $this->resultsCount;
    }
}
