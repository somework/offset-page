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
 * Convenience adapter for callback-based data sources.
 *
 * Use this when you want to provide data via a simple callback function
 * instead of implementing the SourceInterface directly.
 *
 * Your callback receives (page, pageSize) parameters and should return a Generator.
 *
 * @template T
 *
 * @implements SourceInterface<T>
 */
class SourceCallbackAdapter implements SourceInterface
{
    /**
     * @param callable(int, int): \Generator<T> $callback
     */
    public function __construct(private $callback)
    {
    }

    /**
     * @return \Generator<T>
     *
     * @throws InvalidPaginationResultException
     */
    public function execute(int $page, int $pageSize): \Generator
    {
        $result = call_user_func($this->callback, $page, $pageSize);
        if (!$result instanceof \Generator) {
            throw InvalidPaginationResultException::forInvalidCallbackResult(
                $result,
                \Generator::class,
                'should return Generator',
            );
        }

        return $result;
    }
}
