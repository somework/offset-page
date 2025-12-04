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
    public function testGood(): void
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

    public function testBad(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $result = new SourceResultCallbackAdapter(function () {
            return 213;
        }, 0);
        $result->generator();
    }

    public function testGetTotalCount(): void
    {
        $result = new SourceResultCallbackAdapter(function () {
            yield 'item';
        }, 42);

        $this->assertEquals(42, $result->getTotalCount());
    }

    public function testGeneratorWithVariousDataTypes(): void
    {
        $data = [1, 'string', 3.14, true, false, null, ['array'], new \stdClass()];
        $result = new SourceResultCallbackAdapter(function () use ($data) {
            foreach ($data as $item) {
                yield $item;
            }
        }, count($data));

        $generated = [];
        foreach ($result->generator() as $item) {
            $generated[] = $item;
        }

        $this->assertEquals($data, $generated);
        $this->assertEquals(count($data), $result->getTotalCount());
    }

    public function testGeneratorWithLargeDataset(): void
    {
        $largeData = range(1, 10000);
        $result = new SourceResultCallbackAdapter(function () use ($largeData) {
            foreach ($largeData as $item) {
                yield $item;
            }
        }, count($largeData));

        $this->assertEquals(count($largeData), $result->getTotalCount());

        $count = 0;
        foreach ($result->generator() as $item) {
            $this->assertEquals($largeData[$count], $item);
            $count++;
        }
        $this->assertEquals(10000, $count);
    }

    public function testGeneratorWithZeroTotalCount(): void
    {
        $result = new SourceResultCallbackAdapter(function () {
            // Empty generator function - contains yield but never executes it
            if (false) {
                yield;
            }
        }, 0);

        $this->assertEquals(0, $result->getTotalCount());

        $generated = [];
        foreach ($result->generator() as $item) {
            $generated[] = $item;
        }
        $this->assertEquals([], $generated);
    }

    public function testGeneratorWithNegativeTotalCount(): void
    {
        $result = new SourceResultCallbackAdapter(function () {
            yield 'item';
        }, -5);

        $this->assertEquals(-5, $result->getTotalCount());

        $generated = [];
        foreach ($result->generator() as $item) {
            $generated[] = $item;
        }
        $this->assertEquals(['item'], $generated);
    }

    public function testGeneratorWithExceptionInCallback(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Callback failed');

        $result = new SourceResultCallbackAdapter(function () {
            throw new \RuntimeException('Callback failed');
        }, 1);

        // Exception should be thrown when iterating
        foreach ($result->generator() as $item) {
            // Should not reach here
        }
    }

    public function testGeneratorWithComplexCallbackLogic(): void
    {
        $result = new SourceResultCallbackAdapter(function () {
            for ($i = 0; $i < 5; $i++) {
                if ($i === 2) {
                    yield 'special_' . $i;
                } else {
                    yield 'normal_' . $i;
                }
            }
        }, 10); // Different total count

        $expected = ['normal_0', 'normal_1', 'special_2', 'normal_3', 'normal_4'];
        $generated = [];
        foreach ($result->generator() as $item) {
            $generated[] = $item;
        }

        $this->assertEquals($expected, $generated);
        $this->assertEquals(10, $result->getTotalCount());
    }

    public function testGeneratorMultipleIterations(): void
    {
        $result = new SourceResultCallbackAdapter(function () {
            yield 'a';
            yield 'b';
        }, 2);

        // First iteration
        $firstRun = [];
        foreach ($result->generator() as $item) {
            $firstRun[] = $item;
        }
        $this->assertEquals(['a', 'b'], $firstRun);

        // Second iteration should work the same (new generator)
        $secondRun = [];
        foreach ($result->generator() as $item) {
            $secondRun[] = $item;
        }
        $this->assertEquals(['a', 'b'], $secondRun);
    }

    public function testGeneratorWithEarlyTermination(): void
    {
        $result = new SourceResultCallbackAdapter(function () {
            yield 'first';
            yield 'second';
            yield 'third';
            yield 'fourth';
        }, 4);

        $generated = [];
        foreach ($result->generator() as $item) {
            $generated[] = $item;
            if ($item === 'second') {
                break; // Early termination
            }
        }

        $this->assertEquals(['first', 'second'], $generated);
        $this->assertEquals(4, $result->getTotalCount());
    }

    public function testGeneratorMemoryEfficiency(): void
    {
        // Test that generators are memory efficient
        $result = new SourceResultCallbackAdapter(function () {
            for ($i = 0; $i < 1000; $i++) {
                yield str_repeat('x', 100); // 100 char strings
            }
        }, 1000);

        $memoryBefore = memory_get_usage();
        $count = 0;

        foreach ($result->generator() as $item) {
            $count++;
            // Check memory usage every 100 items
            if ($count % 100 === 0) {
                $memoryNow = memory_get_usage();
                // Memory should not grow excessively (allow some growth for test overhead)
                $this->assertLessThan($memoryBefore + 1024 * 500, $memoryNow); // Less than 500KB increase
            }
        }

        $this->assertEquals(1000, $count);
    }

    public function testGeneratorWithNestedData(): void
    {
        $nestedData = [
            ['id' => 1, 'data' => ['nested' => 'value1']],
            ['id' => 2, 'data' => ['nested' => 'value2']],
        ];

        $result = new SourceResultCallbackAdapter(function () use ($nestedData) {
            foreach ($nestedData as $item) {
                yield $item;
            }
        }, count($nestedData));

        $generated = [];
        foreach ($result->generator() as $item) {
            $generated[] = $item;
        }

        $this->assertEquals($nestedData, $generated);
        $this->assertEquals(2, $result->getTotalCount());
    }
}
