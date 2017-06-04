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

use PHPUnit\Framework\TestCase;
use SomeWork\OffsetPage\SourceResultCallbackAdapter;

class SourceResultCallbackAdapterTest extends TestCase
{
    public function testGood()
    {
        $dataSet = [1, 2, 3, 4, 5, 6, 7, 8, 9, 0];

        $result = new SourceResultCallbackAdapter(function () use ($dataSet) {
            foreach ($dataSet as $item) {
                yield $item;
            }
        }, count($dataSet));

        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }
        $this->assertEquals($dataSet, $data);
    }

    public function testBad()
    {
        $this->setExpectedException(\RuntimeException::class);
        $result = new SourceResultCallbackAdapter(function () {
            return 213;
        }, 0);
        $result->generator();
    }
}
