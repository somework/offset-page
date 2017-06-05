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

class SourceCallbackAdapter implements SourceInterface
{
    /**
     * @var callable
     */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param $page
     * @param $pageSize
     *
     * @throws \UnexpectedValueException
     *
     * @return SourceResultInterface
     */
    public function execute($page, $pageSize)
    {
        $result = call_user_func($this->callback, $page, $pageSize);
        if (!is_object($result) || !$result instanceof SourceResultInterface) {
            throw new \UnexpectedValueException('Callback should return SourceResultInterface object');
        }

        return $result;
    }
}
