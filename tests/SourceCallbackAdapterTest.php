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
    public function testGood(): void
    {
        $source = new SourceCallbackAdapter(function () {
            return new ArraySourceResult([1, 2, 3, 4, 5], 5);
        });

        $result = $source->execute(0, 0);

        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }
        $this->assertEquals([1, 2, 3, 4, 5], $data);
    }

    public function testBad(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $source = new SourceCallbackAdapter(function () {
            return '2';
        });

        $source->execute(0, 0);
    }

    public function testExecuteWithVariousParameters(): void
    {
        $source = new SourceCallbackAdapter(function (int $page, int $size) {
            return new ArraySourceResult(["page{$page}_size{$size}"], 1);
        });

        $result = $source->execute(5, 20);
        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['page5_size20'], $data);
    }

    public function testExecuteWithZeroPageAndSize(): void
    {
        $source = new SourceCallbackAdapter(function (int $page, int $size) {
            $this->assertEquals(0, $page);
            $this->assertEquals(0, $size);

            return new ArraySourceResult(['zero_params'], 1);
        });

        $result = $source->execute(0, 0);
        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['zero_params'], $data);
    }

    public function testExecuteWithLargeParameters(): void
    {
        $source = new SourceCallbackAdapter(function (int $page, int $size) {
            $this->assertEquals(1000, $page);
            $this->assertEquals(5000, $size);

            return new ArraySourceResult(['large_params'], 1);
        });

        $result = $source->execute(1000, 5000);
        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['large_params'], $data);
    }

    public function testExecuteWithNegativeParameters(): void
    {
        $source = new SourceCallbackAdapter(function (int $page, int $size) {
            $this->assertEquals(-1, $page);
            $this->assertEquals(-10, $size);

            return new ArraySourceResult(['negative_params'], 1);
        });

        $result = $source->execute(-1, -10);
        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['negative_params'], $data);
    }

    public function testCallbackReturningNull(): void
    {
        $this->expectException(\SomeWork\OffsetPage\Exception\InvalidPaginationResultException::class);
        $this->expectExceptionMessage('Callback (should return SourceResultInterface object)  must return SomeWork\OffsetPage\SourceResultInterface, got NULL');

        $source = new SourceCallbackAdapter(function () {
            return null;
        });

        $source->execute(1, 1);
    }

    public function testCallbackReturningArray(): void
    {
        $this->expectException(\SomeWork\OffsetPage\Exception\InvalidPaginationResultException::class);
        $this->expectExceptionMessage('Callback (should return SourceResultInterface object)  must return SomeWork\OffsetPage\SourceResultInterface, got array');

        $source = new SourceCallbackAdapter(function () {
            return ['not', 'an', 'object'];
        });

        $source->execute(1, 1);
    }

    public function testCallbackReturningStdClass(): void
    {
        $this->expectException(\SomeWork\OffsetPage\Exception\InvalidPaginationResultException::class);
        $this->expectExceptionMessage('Callback (should return SourceResultInterface object)  must return SomeWork\OffsetPage\SourceResultInterface, got stdClass');

        $source = new SourceCallbackAdapter(function () {
            return new \stdClass();
        });

        $source->execute(1, 1);
    }

    public function testCallbackThrowingException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Callback failed');

        $source = new SourceCallbackAdapter(function () {
            throw new \RuntimeException('Callback failed');
        });

        $source->execute(1, 1);
    }

    public function testCallbackWithComplexLogic(): void
    {
        $callCount = 0;
        $source = new SourceCallbackAdapter(function (int $page, int $size) use (&$callCount) {
            $callCount++;
            $data = [];

            // Simulate pagination logic
            for ($i = 0; $i < $size; $i++) {
                $data[] = "page{$page}_item".($i + 1);
            }

            return new ArraySourceResult($data, 100); // Total of 100 items
        });

        $result = $source->execute(2, 3);
        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['page2_item1', 'page2_item2', 'page2_item3'], $data);
        $this->assertEquals(1, $callCount);
    }

    public function testCallbackReturningEmptyResult(): void
    {
        $source = new SourceCallbackAdapter(function () {
            return new ArraySourceResult([], 0);
        });

        $result = $source->execute(1, 10);
        $data = [];
        foreach ($result->generator() as $item) {
            $data[] = $item;
        }

        $this->assertEquals([], $data);
    }

    public function testMultipleExecuteCalls(): void
    {
        $callLog = [];
        $source = new SourceCallbackAdapter(function (int $page, int $size) use (&$callLog) {
            $callLog[] = ['page' => $page, 'size' => $size];

            return new ArraySourceResult(['call_'.count($callLog)], 1);
        });

        // First call
        $result1 = $source->execute(1, 5);
        $data1 = [];
        foreach ($result1->generator() as $item) {
            $data1[] = $item;
        }

        // Second call
        $result2 = $source->execute(2, 10);
        $data2 = [];
        foreach ($result2->generator() as $item) {
            $data2[] = $item;
        }

        $this->assertEquals(['call_1'], $data1);
        $this->assertEquals(['call_2'], $data2);
        $this->assertEquals([
            ['page' => 1, 'size' => 5],
            ['page' => 2, 'size' => 10],
        ], $callLog);
    }
}
