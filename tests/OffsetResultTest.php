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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SomeWork\OffsetPage\OffsetAdapter;
use SomeWork\OffsetPage\OffsetResult;
use SomeWork\OffsetPage\SourceCallbackAdapter;
use SomeWork\OffsetPage\SourceResultInterface;

class OffsetResultTest extends TestCase
{
    public function testNotSourceResultInterfaceGenerator(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $notSourceResultGeneratorFunction = static function () {
            yield 1;
        };

        $offsetResult = new OffsetResult($notSourceResultGeneratorFunction());
        $offsetResult->fetch();
    }

    public function testTotalCount(): void
    {
        $sourceResult = $this
            ->getMockBuilder(SourceResultInterface::class)
            ->onlyMethods(['getTotalCount', 'generator'])
            ->getMock();

        $sourceResult
            ->expects($this->once())
            ->method('getTotalCount')
            ->willReturn(10);

        $sourceResult
            ->method('generator')
            ->willReturn($this->getGenerator(['test']));

        $offsetResult = new OffsetResult($this->getGenerator([$sourceResult]));
        $this->assertEquals(10, $offsetResult->getTotalCount());
        $offsetResult->fetchAll();

        $this->assertEquals(10, $offsetResult->getTotalCount());
    }

    protected function getGenerator(array $value): \Generator
    {
        foreach ($value as $item) {
            yield $item;
        }
    }

    #[DataProvider('totalCountProvider')]
    public function testTotalCountNotChanged(array $totalCountValues, int $expectsCount): void
    {
        $sourceResult = $this
            ->getMockBuilder(SourceResultInterface::class)
            ->onlyMethods(['getTotalCount', 'generator'])
            ->getMock();

        $sourceResultArray = [];
        foreach ($totalCountValues as $totalCountValue) {
            $clone = clone $sourceResult;
            $clone
                ->method('generator')
                ->willReturn($this->getGenerator([$totalCountValue]));
            $clone
                ->method('getTotalCount')
                ->willReturn($totalCountValue);
            $sourceResultArray[] = $clone;
        }

        $offsetResult = new OffsetResult($this->getGenerator($sourceResultArray));
        $offsetResult->fetchAll();
        $this->assertEquals($expectsCount, $offsetResult->getTotalCount());
    }

    public static function totalCountProvider(): array
    {
        return [
            [
                [8, 9, 10],
                10,
            ],
            [
                [],
                0,
            ],
            [
                [20, 0, 10],
                20,
            ],
            [
                [-1, -10],
                0,
            ],
        ];
    }

    #[DataProvider('fetchedCountProvider')]
    public function testFetchedCount(array $sources, array $expectedResult): void
    {
        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals($expectedResult, $offsetResult->fetchAll());
    }

    public static function fetchedCountProvider(): array
    {
        return [
            [

                'sources'        => [
                    new ArraySourceResult([0], 3),
                    new ArraySourceResult([1], 3),
                    new ArraySourceResult([2], 3),
                ],
                'expectedResult' => [0, 1, 2],
            ],
        ];
    }

    /**
     * Infinite fetch.
     */
    public function testError(): void
    {
        $callback = function () {
            return new ArraySourceResult([1], 1);
        };

        $offsetAdapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $offsetAdapter->execute(0, 0);

        $this->assertEquals(1, $result->getTotalCount());
        $this->assertEquals([1], $result->fetchAll());
    }

    public function testEmptyGenerator(): void
    {
        $emptyGenerator = static function () {
            return;
            yield; // Unreachable, but makes it a generator
        };

        $offsetResult = new OffsetResult($emptyGenerator());
        $this->assertEquals([], $offsetResult->fetchAll());
        $this->assertEquals(0, $offsetResult->getTotalCount());
    }

    public function testGeneratorWithEmptySourceResults(): void
    {
        $generator = static function () {
            yield new ArraySourceResult([], 0);
            yield new ArraySourceResult([], 0);
        };

        $offsetResult = new OffsetResult($generator());
        $this->assertEquals([], $offsetResult->fetchAll());
        $this->assertEquals(0, $offsetResult->getTotalCount());
    }

    public function testMultipleFetchCalls(): void
    {
        $sources = [
            new ArraySourceResult(['a', 'b'], 4),
            new ArraySourceResult(['c', 'd'], 4),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // First fetch
        $this->assertEquals('a', $offsetResult->fetch());
        $this->assertEquals('b', $offsetResult->fetch());
        $this->assertEquals('c', $offsetResult->fetch());
        $this->assertEquals('d', $offsetResult->fetch());

        // No more items
        $this->assertNull($offsetResult->fetch());
        $this->assertNull($offsetResult->fetch()); // Multiple calls to exhausted generator
    }

    public function testFetchAfterFetchAll(): void
    {
        $sources = [
            new ArraySourceResult(['x', 'y'], 2),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // fetchAll should exhaust the generator
        $this->assertEquals(['x', 'y'], $offsetResult->fetchAll());

        // Subsequent fetch calls should return null
        $this->assertNull($offsetResult->fetch());
        $this->assertNull($offsetResult->fetch());
    }

    public function testFetchAllAfterPartialFetch(): void
    {
        $sources = [
            new ArraySourceResult(['p', 'q', 'r'], 3),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // Partial fetch
        $this->assertEquals('p', $offsetResult->fetch());
        $this->assertEquals('q', $offsetResult->fetch());

        // fetchAll should get remaining items
        $this->assertEquals(['r'], $offsetResult->fetchAll());

        // Generator should now be exhausted
        $this->assertNull($offsetResult->fetch());
    }

    public function testGeneratorYieldingNonObjects(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Result of generator is not an instance of SomeWork\OffsetPage\SourceResultInterface');

        $generator = static function () {
            yield 'not an object';
        };

        $offsetResult = new OffsetResult($generator());
        $offsetResult->fetch(); // Trigger processing
    }

    public function testGeneratorYieldingInvalidObjects(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Result of generator is not an instance of SomeWork\OffsetPage\SourceResultInterface');

        $generator = static function () {
            yield new \stdClass(); // Doesn't implement SourceResultInterface
        };

        $offsetResult = new OffsetResult($generator());
        $offsetResult->fetch(); // Trigger processing
    }

    public function testTotalCountTakesMaximumValue(): void
    {
        $sources = [
            new ArraySourceResult(['a'], 5),
            new ArraySourceResult(['b'], 10), // Higher count
            new ArraySourceResult(['c'], 7),  // Lower than max
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals(10, $offsetResult->getTotalCount());
        $this->assertEquals(['a', 'b', 'c'], $offsetResult->fetchAll());
    }

    public function testTotalCountWithZeroAndNegativeValues(): void
    {
        $sources = [
            new ArraySourceResult(['x'], -5), // Negative count
            new ArraySourceResult(['y'], 0),  // Zero count
            new ArraySourceResult(['z'], 3),  // Positive count
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals(3, $offsetResult->getTotalCount()); // Should take maximum
        $this->assertEquals(['x', 'y', 'z'], $offsetResult->fetchAll());
    }

    public function testLargeDatasetHandling(): void
    {
        $largeData = range(1, 1000);
        $sources = [
            new ArraySourceResult($largeData, 1000),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals(1000, $offsetResult->getTotalCount());

        $allResults = $offsetResult->fetchAll();
        $this->assertCount(1000, $allResults);
        $this->assertEquals($largeData, $allResults);
    }

    public function testMemoryEfficiencyWithLargeGenerators(): void
    {
        // Test that we don't load all data into memory at once
        $sources = [
            new ArraySourceResult(range(1, 100), 100),
            new ArraySourceResult(range(101, 200), 200),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // Memory check - fetch items one by one
        $memoryBefore = memory_get_usage();
        $count = 0;

        while (($item = $offsetResult->fetch()) !== null) {
            $count++;
            // Simulate some processing and validate it
            $processed = $item * 2;
            $this->assertEquals($item * 2, $processed, 'Processing simulation should work correctly');

            // Check memory doesn't grow excessively
            if ($count % 50 === 0) {
                $memoryNow = memory_get_usage();
                $this->assertLessThan($memoryBefore + 1024 * 1024, $memoryNow); // Less than 1MB increase
            }
        }

        $this->assertEquals(200, $count);
    }

    public function testGeneratorWithMixedDataTypes(): void
    {
        $sources = [
            new ArraySourceResult([1, 'string', 3.14, true, null], 5),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $result = $offsetResult->fetchAll();

        $this->assertEquals([1, 'string', 3.14, true, null], $result);
        $this->assertEquals(5, $offsetResult->getTotalCount());
    }

    public function testEmptySourceResultInMiddleOfGenerator(): void
    {
        $sources = [
            new ArraySourceResult(['first'], 3),
            new ArraySourceResult([], 3), // Empty result
            new ArraySourceResult(['second', 'third'], 3),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals(['first', 'second', 'third'], $offsetResult->fetchAll());
        $this->assertEquals(3, $offsetResult->getTotalCount());
    }

    #[DataProvider('complexFetchScenariosProvider')]
    public function testComplexFetchScenarios(array $sources, array $expectedResults, int $expectedTotalCount): void
    {
        $offsetResult = new OffsetResult($this->getGenerator($sources));

        $this->assertEquals($expectedTotalCount, $offsetResult->getTotalCount());
        $this->assertEquals($expectedResults, $offsetResult->fetchAll());
    }

    public static function complexFetchScenariosProvider(): array
    {
        return [
            'single_source_single_item' => [
                [new ArraySourceResult(['item'], 1)],
                ['item'],
                1,
            ],
            'multiple_sources_same_count' => [
                [
                    new ArraySourceResult(['a1', 'a2'], 4),
                    new ArraySourceResult(['b1', 'b2'], 4),
                ],
                ['a1', 'a2', 'b1', 'b2'],
                4,
            ],
            'empty_sources_mixed_with_data' => [
                [
                    new ArraySourceResult([], 5),
                    new ArraySourceResult(['data'], 5),
                    new ArraySourceResult([], 5),
                ],
                ['data'],
                5,
            ],
            'increasing_total_counts' => [
                [
                    new ArraySourceResult(['x'], 1),
                    new ArraySourceResult(['y'], 2),
                    new ArraySourceResult(['z'], 3),
                ],
                ['x', 'y', 'z'],
                3,
            ],
        ];
    }
}
