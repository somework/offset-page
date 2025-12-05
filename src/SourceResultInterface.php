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

use SomeWork\OffsetPage\Exception\InvalidPaginationResultException;

/**
 * @template T
 */
interface SourceResultInterface
{
    /**
     * @throws InvalidPaginationResultException
     *
     * @return \Generator<T>
     */
    public function generator(): \Generator;
}
