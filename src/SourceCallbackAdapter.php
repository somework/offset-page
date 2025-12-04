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
 * @implements SourceInterface<T>
 */
class SourceCallbackAdapter implements SourceInterface
{
    /**
     * @param callable(int, int): SourceResultInterface<T> $callback
     */
    public function __construct(private $callback)
    {
    }

    /**
     * @return SourceResultInterface<T>
     */
    public function execute(int $page, int $pageSize): SourceResultInterface
    {
        $result = call_user_func($this->callback, $page, $pageSize);
        if (!is_object($result) || !$result instanceof SourceResultInterface) {
            throw new \UnexpectedValueException('Callback should return SourceResultInterface object');
        }

        return $result;
    }
}
