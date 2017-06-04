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

class ArraySource implements SourceInterface
{
    /**
     * @var array
     */
    protected $data;

    /**
     * ArraySource constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param $page
     * @param $pageSize
     *
     * @return SourceResultInterface
     */
    public function execute($page, $pageSize)
    {
        return new ArraySourceResult(
            array_slice($this->data, ($page - 1) * $pageSize, $pageSize),
            count($this->data)
        );
    }
}
