<?php

/*
 * This file is part of the SomeWork/OffsetPage package.
 *
 * (c) Pinchuk Igor <i.pinchuk.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SomeWork\OffsetPage;

class SourceResultCallbackAdapter implements SourceResultInterface
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * @var int
     */
    private $totalCount;

    public function __construct(callable $callback, $totalCount)
    {
        $this->callback = $callback;
        $this->totalCount = (int) $totalCount;
    }

    /**
     * @throws \RuntimeException
     *
     * @return \Generator
     */
    public function generator()
    {
        $result = call_user_func($this->callback);
        if (!is_object($result) || !$result instanceof \Generator) {
            throw new \RuntimeException('Callback result should return Generator');
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }
}
