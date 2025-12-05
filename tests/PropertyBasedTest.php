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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SomeWork\OffsetPage\OffsetAdapter;
use SomeWork\OffsetPage\OffsetResult;
use SomeWork\OffsetPage\SourceCallbackAdapter;

class PropertyBasedTest extends TestCase
{
    public static function randomDataSetsProvider(): array
    {
        $testCases = [];

        // Generate various random datasets for OffsetResult testing only
        for ($i = 0; 3 > $i; $i++) {
            $size = random_int(1, 20);
            $data = [];
            for ($j = 0; $j < $size; $j++) {
                $data[] = random_int(0, 100);
            }

            $testCases["random_$i"] = [$data];
        }

        // Add some specific edge cases
        return array_merge($testCases, [
            'empty'    => [[]],
            'single'   => [['item']],
            'multiple' => [range(1, 10)],
        ]);
    }

    public function testExceptionPropagation(): void
    {
        // Test that exceptions in callbacks are properly propagated
        $source = new SourceCallbackAdapter(function () {
            throw new \DomainException('Domain error');
        });

        $adapter = new OffsetAdapter($source);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Domain error');

        $adapter->execute(0, 1)->fetch();
    }

    #[DataProvider('randomDataSetsProvider')]
    public function testOffsetResultProperties(array $data): void
    {
        // Create a generator that yields the data directly
        $generator = static function () use ($data) {
            yield (static fn () => yield from $data)();
        };

        $result = new OffsetResult($generator());

        // Property 1: fetchAll() should return all available data
        $allData = $result->fetchAll();
        $this->assertEquals($data, $allData);

        // Property 3: getFetchedCount() should be consistent
        $this->assertEquals(count($data), $result->getFetchedCount());
        $this->assertEquals(count($data), $result->getFetchedCount()); // Call again

        // Property 4: fetch() after fetchAll() should return null
        $this->assertNull($result->fetch());
    }

    public function testSourceCallbackAdapterRobustness(): void
    {
        // Test with callback that returns various invalid types
        $invalidReturns = [
            null,
            'string',
            42,
            [],
            new \stdClass(),
            false,
            0,
            '',
        ];

        foreach ($invalidReturns as $invalidReturn) {
            $source = new SourceCallbackAdapter(fn () => $invalidReturn);

            $exceptionThrown = false;

            try {
                $source->execute(1, 1);
            } catch (\UnexpectedValueException) {
                $exceptionThrown = true;
            }

            $this->assertTrue($exceptionThrown, 'Expected exception for invalid return: '.gettype($invalidReturn));
        }
    }

    #[DataProvider('randomDataSetsProvider')]
    public function testStreamingVsBatchEquivalence(array $data): void
    {
        // Test with two separate OffsetResult instances
        $generator1 = static function () use ($data) {
            yield (static fn () => yield from $data)();
        };
        $generator2 = static function () use ($data) {
            yield (static fn () => yield from $data)();
        };

        $result1 = new OffsetResult($generator1());
        $result2 = new OffsetResult($generator2());

        // Get all data via fetchAll()
        $batchResult = $result1->fetchAll();

        // Get all data via streaming fetch()
        $streamingResult = [];
        while (($item = $result2->fetch()) !== null) {
            $streamingResult[] = $item;
        }

        // Both methods should return identical results
        $this->assertEquals($batchResult, $streamingResult);
        $this->assertEquals($data, $batchResult);
        $this->assertEquals($data, $streamingResult);
    }

    public function testTypeSafety(): void
    {
        // Test that the system handles various data types correctly
        $mixedData = [
            'string',
            42,
            3.14,
            true,
            false,
            ['array'],
            (object) ['key' => 'value'],
        ];

        $source = new ArraySource($mixedData);
        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, count($mixedData));

        $this->assertEquals($mixedData, $result->fetchAll());
        $this->assertEquals(count($mixedData), $result->getFetchedCount());
    }
}
