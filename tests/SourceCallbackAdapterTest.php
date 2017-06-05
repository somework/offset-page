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
use SomeWork\OffsetPage\SourceCallbackAdapter;

class SourceCallbackAdapterTest extends TestCase
{
    public function testGood()
    {
        $source = new SourceCallbackAdapter(function ($page, $size) {
            return new ArraySourceResult([1, 2, 3, 4, 5], 5);
        });

        $result = $source->execute(0, 0);
        $this->assertEquals(5, $result->getTotalCount());

        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }
        $this->assertEquals([1, 2, 3, 4, 5], $data);
    }

    public function testBad()
    {
        $this->setExpectedException(\UnexpectedValueException::class);
        $source = new SourceCallbackAdapter(function ($page, $size) {
            return '2';
        });

        $source->execute(0, 0);
    }
}
