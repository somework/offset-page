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

use SomeWork\OffsetPage\SourceResultInterface;

class ArraySourceResult implements SourceResultInterface
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var int
     */
    protected $totalCount;

    /**
     * ArraySourceResult constructor.
     *
     * @param array $data
     * @param int   $totalCount
     */
    public function __construct(array $data, $totalCount)
    {
        $this->data = $data;
        $this->totalCount = (int) $totalCount;
    }

    /**
     * @return \Generator
     */
    public function generator()
    {
        foreach ($this->data as $item) {
            yield $item;
        }
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }
}
