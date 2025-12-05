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
 *
 * @implements SourceResultInterface<T>
 */
class SourceResultCallbackAdapter implements SourceResultInterface
{
    /**
     * @param callable(): \Generator<T> $callback
     */
    public function __construct(private $callback)
    {
    }

    /**
     * @throws InvalidPaginationResultException
     *
     * @return \Generator<T>
     */
    public function generator(): \Generator
    {
        $result = call_user_func($this->callback);
        if (!$result instanceof \Generator) {
            throw InvalidPaginationResultException::forInvalidCallbackResult(
                $result,
                \Generator::class,
                'result should return Generator',
            );
        }

        return $result;
    }
}
