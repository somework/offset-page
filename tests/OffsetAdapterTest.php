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
    public function testAcceptsValidNowCountParameter(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));
        $result = $adapter->execute(0, 3, 2);

        // Should work with positive nowCount
        $items = $result->fetchAll();
        $this->assertSame([3], $items); // With limit=3 and nowCount=2, only 1 more item needed
        $this->assertSame(1, $result->getFetchedCount());
    }

    public function testAcceptsValidPositiveValues(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));

        // Should not throw an exception with valid positive values
        $result = $adapter->execute(1, 2);
        $items = $result->fetchAll();

        // Should return an array with the expected number of items
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
    }

    public function testAcceptsZeroValuesForAllParameters(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));
        $result = $adapter->execute(0, 0);

        // Should not throw an exception for the valid zero sentinel
        $this->assertIsArray($result->fetchAll());
        $this->assertSame(0, $result->getFetchedCount());
    }

    public function testExceptionProvidesAccessToParameterValues(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        try {
            $adapter->execute(-5, 10);
            $this->fail('Expected InvalidPaginationArgumentException was not thrown');
        } catch (InvalidPaginationArgumentException $e) {
            $this->assertSame(['offset' => -5], $e->getParameters());
            $this->assertSame(-5, $e->getParameter('offset'));
            $this->assertNull($e->getParameter('nonexistent'));
            $this->assertStringContainsString('offset must be greater than or equal to zero, got -5', $e->getMessage());
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
            $this->assertIsInt($e->getCode());
        }

        // Test that we can catch any pagination exception with the interface
        try {
            $adapter->execute(1, 0, 2);
            $this->fail('Expected exception was not thrown');
        } catch (PaginationExceptionInterface) {
            // Successfully caught using the interface
            $this->addToAssertionCount(1);
        }
    }

    public function testGeneratorMethodReturnsGeneratorWithSameData(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(2, 3);
        $generator = $adapter->generator(2, 3);

        // Generator should produce same data as OffsetResult
        $generatorData = iterator_to_array($generator);
        $resultData = $result->fetchAll();

        $this->assertEquals($resultData, $generatorData);
        $this->assertEquals([3, 4, 5], $generatorData);
    }

    public function testGeneratorMethodValidation(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $adapter->generator(-1, 5);
    }

    public function testGeneratorMethodWithLargeDataset(): void
    {
        $data = range(1, 1000);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $generator = $adapter->generator(100, 50);

        $result = iterator_to_array($generator);
        $expected = array_slice($data, 100, 50);

        $this->assertEquals($expected, $result);
        $this->assertCount(50, $result);
    }

    public function testGeneratorMethodWithNowCountParameter(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $generator = $adapter->generator(0, 3, 2);

        $result = iterator_to_array($generator);

        // With nowCount=2, only 1 item should be returned (limit - nowCount)
        $this->assertEquals([3], $result);
        $this->assertCount(1, $result);
    }

    public function testGeneratorMethodWithZeroLimitSentinel(): void
    {
        $adapter = new OffsetAdapter(new ArraySource(range(1, 5)));

        $generator = $adapter->generator(0, 0);

        // Should return empty generator
        $this->assertEquals([], iterator_to_array($generator));
    }

    public function testLoopTerminatesAfterRequestedLimit(): void
    {
        $counter = 0;
        $callback = function (int $page, int $size) use (&$counter) {
            $counter++;
            yield from range(1, $size);
        };

        $adapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $adapter->execute(0, 5);

        $this->assertSame([1, 2, 3, 4, 5], $result->fetchAll());
        $this->assertSame(5, $result->getFetchedCount());
        $this->assertLessThanOrEqual(2, $counter, 'Adapter should not loop endlessly when data exists.');
    }

    public function testNowCountStopsWhenAlreadyEnough(): void
    {
        $data = range(1, 10);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(0, 5, 5);
        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $result->getFetchedCount());
    }

    public function testOffsetGreaterThanLimitNonDivisibleUsesDivisorMapping(): void
    {
        $data = range(1, 100);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(47, 22);
        $expected = array_slice($data, 47, 22);

        $this->assertSame($expected, $result->fetchAll());
        $this->assertSame(22, $result->getFetchedCount());
    }

    public function testOffsetLessThanLimitUsesLogicPaginationAndStopsAtLimit(): void
    {
        $data = range(1, 20);
        $adapter = new OffsetAdapter(new ArraySource($data));

        $result = $adapter->execute(3, 5);

        $this->assertSame([4, 5, 6, 7, 8], $result->fetchAll());
        $this->assertSame(5, $result->getFetchedCount());
    }

    public function testRejectsLimitZeroWhenNowCountProvided(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage(
            'Zero limit is only allowed when both offset and nowCount are also zero (current: offset=0, limit=0, nowCount=5). Zero limit indicates "fetch all remaining items" and can only be used at the start of pagination. For unlimited fetching, use a very large limit value instead.',
        );
        $adapter->execute(0, 0, 5);
    }

    public function testRejectsLimitZeroWhenOffsetOrNowCountProvided(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $adapter->execute(5, 0);
    }

    public function testRejectsLimitZeroWithBothOffsetAndNowCountNonZero(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage(
            'Zero limit is only allowed when both offset and nowCount are also zero (current: offset=1, limit=0, nowCount=1). Zero limit indicates "fetch all remaining items" and can only be used at the start of pagination. For unlimited fetching, use a very large limit value instead.',
        );
        $adapter->execute(1, 0, 1);
    }

    public function testRejectsNegativeArguments(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $adapter->execute(-1, 1);
    }

    public function testRejectsNegativeLimit(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage(
            'limit must be greater than or equal to zero, got -1. Use a non-negative integer to specify the maximum number of items to return.',
        );
        $adapter->execute(0, -1);
    }

    public function testRejectsNegativeNowCount(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage(
            'nowCount must be greater than or equal to zero, got -1. Use a non-negative integer to specify the number of items already fetched.',
        );
        $adapter->execute(0, 5, -1);
    }

    public function testRejectsNegativeOffset(): void
    {
        $adapter = new OffsetAdapter(new ArraySource([]));

        $this->expectException(InvalidPaginationArgumentException::class);
        $this->expectExceptionMessage(
            'offset must be greater than or equal to zero, got -1. Use a non-negative integer to specify the starting position in the dataset.',
        );
        $adapter->execute(-1, 5);
    }

    public function testStopsWhenSourceReturnsEmptyImmediately(): void
    {
        $callback = function (int $page, int $size) {
            // Return empty generator
            yield from [];
        };

        $adapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $adapter->execute(0, 5);

        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $result->getFetchedCount());
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

    public function testZeroLimitSentinelReturnsEmptyResult(): void
    {
        $adapter = new OffsetAdapter(new ArraySource(range(1, 5)));
        $result = $adapter->execute(0, 0);

        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $result->getFetchedCount());
    }

    public function testFromCallbackCreatesAdapterWithCallbackSource(): void
    {
        $data = ['apple', 'banana', 'cherry', 'date', 'elderberry'];
        $callCount = 0;

        $adapter = OffsetAdapter::fromCallback(function (int $page, int $pageSize) use ($data, &$callCount) {
            $callCount++;
            $startIndex = ($page - 1) * $pageSize;

            if ($startIndex >= count($data)) {
                yield from [];
                return;
            }

            $items = array_slice($data, $startIndex, $pageSize);
            yield from $items;
        });

        // Test that the adapter works correctly - request first 3 items
        $result = $adapter->execute(0, 3);
        $items = $result->fetchAll();

        $this->assertSame(['apple', 'banana', 'cherry'], $items);
        $this->assertSame(3, $result->getFetchedCount());
        $this->assertSame(1, $callCount); // Callback should be called once for page 1

        // Reset call count for next test
        $callCount = 0;

        // Test pagination works - request next 2 items
        $result2 = $adapter->execute(3, 2);
        $items2 = $result2->fetchAll();

        $this->assertSame(['date', 'elderberry'], $items2);
        $this->assertSame(2, $result2->getFetchedCount());
        // Note: pagination logic may call callback multiple times to satisfy the request
        $this->assertGreaterThanOrEqual(1, $callCount);
    }

    public function testFromCallbackWithEmptyData(): void
    {
        $adapter = OffsetAdapter::fromCallback(function (int $page, int $pageSize) {
            yield from []; // Always return empty
        });

        $result = $adapter->execute(0, 10);
        $items = $result->fetchAll();

        $this->assertSame([], $items);
        $this->assertSame(0, $result->getFetchedCount());
    }


    public function testExecuteHandlesSourceReturningEmptyGenerator(): void
    {
        // Create a source that returns an empty generator immediately
        $source = new SourceCallbackAdapter(function (int $page, int $pageSize) {
            // Return an empty generator (never yields anything)
            return;
            yield; // This line is never reached
        });

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, 5);

        $items = $result->fetchAll();
        $this->assertSame([], $items);
        $this->assertSame(0, $result->getFetchedCount());
    }

    public function testGeneratorMethodWithEdgeCaseParameters(): void
    {
        $data = ['test'];
        $adapter = new OffsetAdapter(new ArraySource($data));

        // Test generator method with parameters that might trigger edge cases
        $generator = $adapter->generator(0, 1);
        $items = iterator_to_array($generator);

        $this->assertSame(['test'], $items);
    }

    public function testAllMethodsExecutedThroughDifferentPaths(): void
    {
        $data = ['item1', 'item2', 'item3'];
        $adapter = new OffsetAdapter(new ArraySource($data));

        // Test execute method (covers logic, assertArgumentsAreValid, createLimitedGenerator, shouldContinuePagination)
        $result1 = $adapter->execute(0, 2);
        $this->assertSame(['item1', 'item2'], $result1->fetchAll());

        // Test generator method (covers same internal methods)
        $generator = $adapter->generator(1, 2);
        $this->assertSame(['item2', 'item3'], iterator_to_array($generator));

        // Test fetchAll method (covers same internal methods)
        $items = $adapter->fetchAll(2, 1);
        $this->assertSame(['item3'], $items);
    }
}
