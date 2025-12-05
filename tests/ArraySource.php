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

use SomeWork\OffsetPage\SourceInterface;

/**
 * @template T
 *
 * @implements SourceInterface<T>
 */
class ArraySource implements SourceInterface
{
    /**
     * Create a new ArraySource containing the provided items.
     *
     * @param array<T> $data The array of items to expose as the source.
     */
    public function __construct(protected array $data)
    {
    }

    /**
     * Provides the items for a specific page from the internal array.
     *
     * @param int $page     Page number; values less than 1 are treated as 1.
     * @param int $pageSize Number of items per page; if less than or equal to 0 no items are yielded.
     *
     * @return \Generator<T> A generator that yields the items for the requested page.
     */
    public function execute(int $page, int $pageSize): \Generator
    {
        $page = max(1, $page);

        if (0 < $pageSize) {
            yield from array_slice($this->data, ($page - 1) * $pageSize, $pageSize);
        }
    }
}
