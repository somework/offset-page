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
use SomeWork\OffsetPage\SourceResultInterface;

/**
 * @template T
 *
 * @implements SourceInterface<T>
 */
class ArraySource implements SourceInterface
{
    /**
     * @param array<T> $data
     */
    public function __construct(protected array $data)
    {
    }

    /**
     * @return SourceResultInterface<T>
     */
    public function execute(int $page, int $pageSize): SourceResultInterface
    {
        $data = array_slice($this->data, ($page - 1) * $pageSize, $pageSize);

        return new ArraySourceResult(
            $data,
            count($data),
        );
    }
}
