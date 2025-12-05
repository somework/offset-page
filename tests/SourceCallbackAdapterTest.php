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
use SomeWork\OffsetPage\Exception\InvalidPaginationResultException;
use SomeWork\OffsetPage\SourceCallbackAdapter;

class SourceCallbackAdapterTest extends TestCase
{
    public static function invalidCallbackReturnProvider(): array
    {
        return [
            'null' => [null, 'null'],
            'array' => [['not', 'an', 'object'], 'array'],
            'stdClass' => [new \stdClass(), 'stdClass'],
        ];
    }

    public static function parameterTestProvider(): array
    {
        return [
            'various_parameters' => [5, 20, ['page5_size20'], false],
            'zero_parameters' => [0, 0, ['zero_params'], true],
            'large_parameters' => [1000, 5000, ['large_params'], true],
        ];
    }

    public function testBad(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $source = new SourceCallbackAdapter(function () {
            return '2';
        });

        $source->execute(0, 0);
    }

    public function testCallbackReturningEmptyResult(): void
    {
        $source = new SourceCallbackAdapter(function () {
            yield from [];
        });

        $result = $source->execute(1, 10);
        $data = [];
        foreach ($result as $item) {
            $data[] = $item;
        }

        $this->assertEquals([], $data);
    }

    /**
     * @dataProvider invalidCallbackReturnProvider
     */
    public function testCallbackReturningInvalidType($invalidValue, string $expectedType): void
    {
        $this->expectException(InvalidPaginationResultException::class);
        $this->expectExceptionMessage("Callback (should return Generator) must return Generator, got $expectedType");

        $source = new SourceCallbackAdapter(function () use ($invalidValue) {
            return $invalidValue;
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

            // Simulate pagination logic
            for ($i = 0; $i < $size; $i++) {
                yield "page{$page}_item".($i + 1);
            }
        });

        $result = $source->execute(2, 3);
        $data = [];
        foreach ($result as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['page2_item1', 'page2_item2', 'page2_item3'], $data);
        $this->assertEquals(1, $callCount);
    }

    public function testExecuteWithNegativeParameters(): void
    {
        $source = new SourceCallbackAdapter(function (int $page, int $size) {
            $this->assertEquals(-1, $page);
            $this->assertEquals(-10, $size);

            yield 'negative_params';
        });

        $result = $source->execute(-1, -10);
        $data = [];
        foreach ($result as $item) {
            $data[] = $item;
        }

        $this->assertEquals(['negative_params'], $data);
    }

    /**
     * @dataProvider parameterTestProvider
     */
    public function testExecuteWithParameters(int $page, int $size, array $expectedResult, bool $assertParameters): void
    {
        $source = new SourceCallbackAdapter(
            function (int $callbackPage, int $callbackSize) use ($page, $size, $assertParameters) {
                if ($assertParameters) {
                    $this->assertEquals($page, $callbackPage);
                    $this->assertEquals($size, $callbackSize);
                }

                if (5 === $page && 20 === $size) {
                    yield "page{$callbackPage}_size$callbackSize";
                } elseif (0 === $page && 0 === $size) {
                    yield 'zero_params';
                } elseif (1000 === $page && 5000 === $size) {
                    yield 'large_params';
                }
            },
        );

        $result = $source->execute($page, $size);
        $data = [];
        foreach ($result as $item) {
            $data[] = $item;
        }

        $this->assertEquals($expectedResult, $data);
    }

    public function testGood(): void
    {
        $source = new SourceCallbackAdapter(function () {
            yield from [1, 2, 3, 4, 5];
        });

        $result = $source->execute(0, 0);

        $data = [];
        foreach ($result as $item) {
            $data[] = $item;
        }
        $this->assertEquals([1, 2, 3, 4, 5], $data);
    }

    public function testMultipleExecuteCalls(): void
    {
        $callLog = [];
        $source = new SourceCallbackAdapter(function (int $page, int $size) use (&$callLog) {
            $callLog[] = ['page' => $page, 'size' => $size];

            yield 'call_'.count($callLog);
        });

        // First call
        $result1 = $source->execute(1, 5);
        $data1 = [];
        foreach ($result1 as $item) {
            $data1[] = $item;
        }

        // Second call
        $result2 = $source->execute(2, 10);
        $data2 = [];
        foreach ($result2 as $item) {
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
