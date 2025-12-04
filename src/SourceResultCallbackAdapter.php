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

/**
 * @template T
 *
 * @implements SourceResultInterface<T>
 */
class SourceResultCallbackAdapter implements SourceResultInterface
{
    /**
     * @var callable(): \Generator<T>
     */
    private $callback;

    /**
     * @param callable(): \Generator<T> $callback
     */
    public function __construct(callable $callback, protected int $totalCount)
    {
        $this->callback = $callback;
    }

    /**
     * @return \Generator<T>
     */
    public function generator(): \Generator
    {
        $result = call_user_func($this->callback);
        if (!$result instanceof \Generator) {
            throw new \UnexpectedValueException('Callback result should return Generator');
        }

        return $result;
    }

    public function getResultCount(): int
    {
        return $this->totalCount;
    }
}
