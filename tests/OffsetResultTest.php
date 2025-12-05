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

class OffsetResultTest extends TestCase
{
    public static function complexFetchScenariosProvider(): array
    {
        return [
            'single_source_single_item' => [
                [
                    (static fn () => yield from ['item'])(),
                ],
                ['item'],
                1,
            ],
            'multiple_sources_same_count' => [
                [
                    (static fn () => yield from ['a1', 'a2'])(),
                    (static fn () => yield from ['b1', 'b2'])(),
                ],
                ['a1', 'a2', 'b1', 'b2'],
                4,
            ],
            'empty_sources_mixed_with_data' => [
                [
                    (static fn () => yield from [])(),
                    (static fn () => yield from ['data'])(),
                    (static fn () => yield from [])(),
                ],
                ['data'],
                1,
            ],
        ];
    }

    public static function fetchedCountProvider(): array
    {
        return [
            [

                'sources' => [
                    (static fn () => yield from [0])(),
                    (static fn () => yield from [1])(),
                    (static fn () => yield from [2])(),
                ],
                'expectedResult' => [0, 1, 2],
            ],
        ];
    }

    #[DataProvider('complexFetchScenariosProvider')]
    public function testComplexFetchScenarios(array $sources, array $expectedResults, int $expectedTotalCount): void
    {
        $offsetResult = new OffsetResult($this->getGenerator($sources));

        $this->assertEquals($expectedResults, $offsetResult->fetchAll());
        $this->assertEquals($expectedTotalCount, $offsetResult->getFetchedCount());
    }

    public function testEmptyGenerator(): void
    {
        $emptyGenerator = static function () {
            yield from [];
        };

        $offsetResult = new OffsetResult($emptyGenerator());
        $this->assertEquals([], $offsetResult->fetchAll());
        $this->assertEquals(0, $offsetResult->getFetchedCount());
    }

    public function testEmptySourceResultInMiddleOfGenerator(): void
    {
        $sources = [
            $this->getGenerator(['first']),
            $this->getGenerator([]),
            $this->getGenerator(['second', 'third']),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals(['first', 'second', 'third'], $offsetResult->fetchAll());
        $this->assertEquals(3, $offsetResult->getFetchedCount());
    }

    public function testEmptyStaticFactory(): void
    {
        $emptyResult = OffsetResult::empty();

        $this->assertEquals([], $emptyResult->fetchAll());
        $this->assertEquals(0, $emptyResult->getFetchedCount());
        $this->assertNull($emptyResult->fetch());
    }

    public function testEmptyStaticFactoryGeneratorMethod(): void
    {
        $emptyResult = OffsetResult::empty();

        $generator = $emptyResult->generator();

        $this->assertEquals([], iterator_to_array($generator));
    }

    public function testEmptyStaticFactoryMultipleCalls(): void
    {
        $empty1 = OffsetResult::empty();
        $empty2 = OffsetResult::empty();

        // Should be different instances but behave identically
        $this->assertNotSame($empty1, $empty2);
        $this->assertEquals([], $empty1->fetchAll());
        $this->assertEquals([], $empty2->fetchAll());
        $this->assertEquals(0, $empty1->getFetchedCount());
        $this->assertEquals(0, $empty2->getFetchedCount());
    }

    /**
     * Infinite fetch.
     */
    public function testError(): void
    {
        $callback = function () {
            yield 1;
        };

        $offsetAdapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $offsetAdapter->execute(0, 0);

        $this->assertEquals([], $result->fetchAll());
        $this->assertEquals(0, $result->getFetchedCount());
    }

    public function testFetchAfterFetchAll(): void
    {
        $sources = [
            $this->getGenerator(['x', 'y']),
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
            $this->getGenerator(['p', 'q', 'r']),
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

    #[DataProvider('fetchedCountProvider')]
    public function testFetchedCount(array $sources, array $expectedResult): void
    {
        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals($expectedResult, $offsetResult->fetchAll());
    }

    public function testGeneratorMethodAfterFetchAll(): void
    {
        $data = ['p', 'q', 'r'];
        $sources = [$this->getGenerator($data)];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // Exhaust the result
        $this->assertEquals($data, $offsetResult->fetchAll());

        // Generator method returns the same consumed generator
        $generator = $offsetResult->generator();

        // Should throw exception when trying to iterate consumed generator
        $this->expectException(\Exception::class);
        iterator_to_array($generator);
    }

    public function testGeneratorMethodMultipleCalls(): void
    {
        $data = ['first', 'second'];
        $sources = [$this->getGenerator($data)];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // First generator call
        $gen1 = $offsetResult->generator();
        $result1 = iterator_to_array($gen1);
        $this->assertEquals($data, $result1);

        // Second generator call returns same consumed generator
        $gen2 = $offsetResult->generator();

        // Should throw exception when trying to iterate consumed generator
        $this->expectException(\Exception::class);
        iterator_to_array($gen2);
    }

    public function testGeneratorMethodReturnsGenerator(): void
    {
        $data = ['a', 'b', 'c'];
        $sources = [$this->getGenerator($data)];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $generator = $offsetResult->generator();

        $this->assertEquals($data, iterator_to_array($generator));
    }

    public function testGeneratorMethodWithEmptyResult(): void
    {
        $emptyResult = OffsetResult::empty();
        $generator = $emptyResult->generator();

        $this->assertEquals([], iterator_to_array($generator));
    }

    public function testGeneratorMethodWithLargeData(): void
    {
        $largeData = range(1, 1000);
        $sources = [$this->getGenerator($largeData)];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $generator = $offsetResult->generator();

        $result = iterator_to_array($generator);

        $this->assertEquals($largeData, $result);
        $this->assertCount(1000, $result);
    }

    public function testGeneratorMethodWithPartialConsumption(): void
    {
        $data = ['x', 'y', 'z'];
        $sources = [$this->getGenerator($data)];

        $offsetResult = new OffsetResult($this->getGenerator($sources));

        // Get generator before consuming OffsetResult
        $generator = $offsetResult->generator();

        // Consume one item from generator first
        $this->assertEquals('x', $generator->current());
        $generator->next();

        // Get remaining items
        $remaining = [];
        while ($generator->valid()) {
            $remaining[] = $generator->current();
            $generator->next();
        }

        $this->assertEquals(['y', 'z'], $remaining);
    }

    public function testGeneratorWithEmptySourceResults(): void
    {
        $generator = static function () {
            yield (static fn () => yield from [])();
            yield (static fn () => yield from [])();
        };

        $offsetResult = new OffsetResult($generator());
        $this->assertEquals([], $offsetResult->fetchAll());
        $this->assertEquals(0, $offsetResult->getFetchedCount());
    }

    public function testGeneratorWithMixedDataTypes(): void
    {
        $data = [1, 'string', 3.14, true];
        $sources = [
            $this->getGenerator($data),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $result = $offsetResult->fetchAll();

        $this->assertEquals($data, $result);
        $this->assertEquals(4, $offsetResult->getFetchedCount());
    }

    public function testLargeDatasetHandling(): void
    {
        $largeData = range(1, 1000);
        $sources = [
            $this->getGenerator($largeData),
        ];

        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals(0, $offsetResult->getFetchedCount());

        $allResults = $offsetResult->fetchAll();
        $this->assertCount(1000, $allResults);
        $this->assertEquals($largeData, $allResults);
    }

    public function testMemoryEfficiencyWithLargeGenerators(): void
    {
        // Test that we don't load all data into memory at once
        $sources = [
            $this->getGenerator(range(1, 100)),
            $this->getGenerator(range(101, 200)),
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
            if (0 === $count % 50) {
                $memoryNow = memory_get_usage();
                $this->assertLessThan($memoryBefore + 1024 * 1024, $memoryNow); // Less than 1MB increase
            }
        }

        $this->assertEquals(200, $count);
    }

    public function testMultipleFetchCalls(): void
    {
        $sources = [
            $this->getGenerator(['a']),
            $this->getGenerator(['b']),
            $this->getGenerator(['c']),
            $this->getGenerator(['d']),
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

    protected function getGenerator(array $value): \Generator
    {
        yield from $value;
    }
}
