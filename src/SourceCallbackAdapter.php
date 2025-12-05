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
     * Wraps a callable data source for use as a SourceInterface implementation.
     *
     * @param callable(int, int): \Generator<T> $callback A callable that accepts the 1-based page number and page size, and yields items of type `T`.
     */
    public function __construct(private $callback)
    {
    }

    /**
     * Invoke the configured callback to produce a page of results as a Generator.
     *
     * Calls the adapter's callback with the provided page and page size and returns the resulting Generator.
     *
     * @throws InvalidPaginationResultException If the callback does not return a `\Generator`.
     *
     * @return \Generator<T> A Generator that yields page results of type `T`.
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
