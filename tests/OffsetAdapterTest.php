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

use PHPUnit\Framework\TestCase;
use SomeWork\OffsetPage\Exception\InvalidPaginationArgumentException;
use SomeWork\OffsetPage\Exception\PaginationExceptionInterface;
use SomeWork\OffsetPage\OffsetAdapter;
use SomeWork\OffsetPage\SourceCallbackAdapter;

class OffsetAdapterTest extends TestCase
{
    public function testRejectsNegativeArguments(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $adapter->execute(-1, 1);
    }

    public function testRejectsLimitZeroWhenOffsetOrNowCountProvided(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
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

    public function testRejectsNegativeOffset(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage('offset must be greater than or equal to zero, got -1. Use a non-negative integer to specify the starting position in the dataset.');
        $adapter->execute(-1, 5);
    }

    public function testRejectsNegativeLimit(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage('limit must be greater than or equal to zero, got -1. Use a non-negative integer to specify the maximum number of items to return.');
        $adapter->execute(0, -1);
    }

    public function testRejectsNegativeNowCount(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage('nowCount must be greater than or equal to zero, got -1. Use a non-negative integer to specify the number of items already fetched.');
        $adapter->execute(0, 5, -1);
    }

    public function testRejectsLimitZeroWhenNowCountProvided(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage('Zero limit is only allowed when both offset and nowCount are also zero (current: offset=0, limit=0, nowCount=5). Zero limit indicates "fetch all remaining items" and can only be used at the start of pagination. For unlimited fetching, use a very large limit value instead.');
        $adapter->execute(0, 0, 5);
    }

    public function testAcceptsValidPositiveValues(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));

        // Should not throw an exception with valid positive values
        $result = $adapter->execute(1, 2, 0);
        $items = $result->fetchAll();

        // Should return an array with the expected number of items
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
    }

    public function testAcceptsZeroValuesForAllParameters(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));
        $result = $adapter->execute(0, 0, 0);

        // Should not throw an exception for the valid zero sentinel
        $this->assertIsArray($result->fetchAll());
        $this->assertSame(0, $result->getTotalCount());
    }

    public function testAcceptsValidNowCountParameter(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));
        $result = $adapter->execute(0, 3, 2);

        // Should work with positive nowCount
        $this->assertIsArray($result->fetchAll());
        $this->assertSame(1, $result->getTotalCount()); // Only 1 item should be returned due to nowCount=2
    }

    public function testRejectsLimitZeroWithBothOffsetAndNowCountNonZero(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage('Zero limit is only allowed when both offset and nowCount are also zero (current: offset=1, limit=0, nowCount=1). Zero limit indicates "fetch all remaining items" and can only be used at the start of pagination. For unlimited fetching, use a very large limit value instead.');
        $adapter->execute(1, 0, 1);
    }

    public function testExceptionProvidesAccessToParameterValues(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        try {
            $adapter->execute(-5, 10, 0);
            $this->fail('Expected InvalidPaginationArgumentException was not thrown');
        } catch (InvalidPaginationArgumentException $e) {
            $this->assertSame(['offset' => -5], $e->getParameters());
            $this->assertSame(-5, $e->getParameter('offset'));
            $this->assertNull($e->getParameter('nonexistent'));
            $this->assertStringContainsString('offset must be greater than or equal to zero, got -5', $e->getMessage());
        }
    }

    public function testZeroLimitExceptionProvidesAllParameterValues(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        try {
            $adapter->execute(2, 0, 3);
            $this->fail('Expected InvalidPaginationArgumentException was not thrown');
        } catch (InvalidPaginationArgumentException $e) {
            $expectedParams = ['offset' => 2, 'limit' => 0, 'nowCount' => 3];
            $this->assertSame($expectedParams, $e->getParameters());
            $this->assertSame(2, $e->getParameter('offset'));
            $this->assertSame(0, $e->getParameter('limit'));
            $this->assertSame(3, $e->getParameter('nowCount'));
        }
    }

    public function testExceptionsImplementPaginationExceptionInterface(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        // Test that InvalidPaginationArgumentException implements the interface
        try {
            $adapter->execute(-1, 5);
            $this->fail('Expected exception was not thrown');
        } catch (PaginationExceptionInterface $e) {
            $this->assertInstanceOf(InvalidPaginationArgumentException::class, $e);
            $this->assertInstanceOf(PaginationExceptionInterface::class, $e);
            $this->assertIsString($e->getMessage());
            $this->assertIsInt($e->getCode());
        }

        // Test that we can catch any pagination exception with the interface
        try {
            $adapter->execute(1, 0, 2);
            $this->fail('Expected exception was not thrown');
        } catch (PaginationExceptionInterface) {
            // Successfully caught using the interface
            $this->assertTrue(true);
        }
    }
}
