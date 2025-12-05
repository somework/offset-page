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

namespace SomeWork\OffsetPage\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SomeWork\OffsetPage\OffsetAdapter;
use SomeWork\OffsetPage\SourceCallbackAdapter;

class OffsetAdapterTest extends TestCase
{
    public function testRejectsNegativeArguments(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidArgumentException::class);
        $adapter->execute(-1, 1);
    }

    public function testRejectsLimitZeroWhenOffsetOrNowCountProvided(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidArgumentException::class);
        $adapter->execute(5, 0);
    }

    public function testZeroLimitSentinelReturnsEmptyResult(): void
    {
        $adapter = new OffsetAdapter(new ArraySource(range(1, 5)));
        $result = $adapter->execute(0, 0, 0);

        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $result->getTotalCount());
    }

    public function testStopsWhenSourceReturnsEmptyImmediately(): void
    {
        $callback = function (int $page, int $size) {
            return new ArraySourceResult([]);
        };

        $adapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $adapter->execute(0, 5);

        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $result->getTotalCount());
    }

    public function testOffsetLessThanLimitUsesLogicPaginationAndStopsAtLimit(): void
    {
        $data = range(1, 20);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(3, 5);

        $this->assertSame([4, 5, 6, 7, 8], $result->fetchAll());
        $this->assertSame(5, $result->getTotalCount());
    }

    public function testOffsetGreaterThanLimitNonDivisibleUsesDivisorMapping(): void
    {
        $data = range(1, 100);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(47, 22);
        $expected = array_slice($data, 47, 22);

        $this->assertSame($expected, $result->fetchAll());
        $this->assertSame(22, $result->getTotalCount());
    }

    public function testNowCountStopsWhenAlreadyEnough(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(0, 5, 5);
        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $result->getTotalCount());
    }

    public function testLoopTerminatesAfterRequestedLimit(): void
    {
        $counter = 0;
        $callback = function (int $page, int $size) use (&$counter) {
            $counter++;
            return new ArraySourceResult(range(1, $size));
        };

        $adapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $adapter->execute(0, 5);

        $this->assertSame([1, 2, 3, 4, 5], $result->fetchAll());
        $this->assertSame(5, $result->getTotalCount());
        $this->assertLessThanOrEqual(2, $counter, 'Adapter should not loop endlessly when data exists.');
    }
}
