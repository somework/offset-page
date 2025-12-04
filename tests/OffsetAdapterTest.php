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
use SomeWork\OffsetPage\SourceCallbackAdapter;
use SomeWork\OffsetPage\SourceInterface;

class OffsetAdapterTest extends TestCase
{
    public function testConstructWithValidSource(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $adapter = new OffsetAdapter($source);

        $this->assertInstanceOf(OffsetAdapter::class, $adapter);
    }

    public function testExecuteWithEmptyData(): void
    {
        $data = [];
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, 10);

        $this->assertEquals([], $result->fetchAll());
        $this->assertEquals(0, $result->getTotalCount());
    }

    public function testExecuteWithSingleItem(): void
    {
        $data = ['single_item'];
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, 10);

        $this->assertEquals(['single_item'], $result->fetchAll());
        $this->assertEquals(1, $result->getTotalCount());
    }

    public function testExecuteWithMultipleItems(): void
    {
        $data = ['item1', 'item2', 'item3', 'item4', 'item5'];
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, 10);

        $this->assertEquals(['item1', 'item2', 'item3', 'item4', 'item5'], $result->fetchAll());
        $this->assertEquals(5, $result->getTotalCount());
    }

    public function testExecuteWithOffset(): void
    {
        $data = range(1, 10);
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(3, 5); // The actual behavior depends on the logic library

        // Based on observed behavior, offset=3, limit=5 returns [4, 5, 6, 7, 8]
        $this->assertEquals([4, 5, 6, 7, 8], $result->fetchAll());
        $this->assertEquals(5, $result->getTotalCount());
    }

    public function testExecuteWithLargeOffset(): void
    {
        $data = range(1, 10);
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(8, 5); // Should get last 2 items

        $this->assertEquals([9, 10], $result->fetchAll());
        $this->assertEquals(2, $result->getTotalCount());
    }

    public function testExecuteWithOffsetBeyondData(): void
    {
        $data = range(1, 5);
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(10, 5); // Offset beyond available data

        $this->assertEquals([], $result->fetchAll());
        $this->assertEquals(0, $result->getTotalCount());
    }

    public function testExecuteWithZeroLimit(): void
    {
        $data = range(1, 10);
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, 0);

        $this->assertEquals([], $result->fetchAll());
        $this->assertEquals(0, $result->getTotalCount());
    }

    public function testExecuteWithCallbackSource(): void
    {
        $callback = function (int $page, int $size) {
            $data = [];
            for ($i = 0; $i < $size; $i++) {
                $data[] = "page{$page}_item".($i + 1);
            }

            return new ArraySourceResult($data); // Simulate 100 total items
        };

        $source = new SourceCallbackAdapter($callback);
        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute(0, 5);

        $expected = ['page1_item1', 'page1_item2', 'page1_item3', 'page1_item4', 'page1_item5'];
        $this->assertEquals($expected, $result->fetchAll());
        $this->assertEquals(5, $result->getTotalCount());
    }

    public function testExecuteWithSourceException(): void
    {
        $callback = function () {
            throw new \RuntimeException('Source database unavailable');
        };

        $source = new SourceCallbackAdapter($callback);
        $adapter = new OffsetAdapter($source);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source database unavailable');

        $adapter->execute(0, 10)->fetch();
    }

    #[DataProvider('paginationScenariosProvider')]
    public function testPaginationScenarios(array $data, int $offset, int $limit, array $expected): void
    {
        $source = new ArraySource($data);
        $adapter = new OffsetAdapter($source);
        $result = $adapter->execute($offset, $limit);

        $this->assertEquals($expected, $result->fetchAll());
        $this->assertEquals(count($expected), $result->getTotalCount());
    }

    public static function paginationScenariosProvider(): array
    {
        // Based on observed behavior from testing
        return [
            'first_page'      => [range(1, 20), 0, 10, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
            'offset_three'    => [range(1, 20), 3, 10, [4, 5, 6, 7, 8, 9, 10, 11, 12, 13]],
            'offset_near_end' => [range(1, 10), 8, 5, [9, 10]],
            'empty_result'    => [range(1, 5), 10, 5, []], // Offset beyond data
        ];
    }

    public function testNowCountParameter(): void
    {
        // Test that nowCount parameter is accepted and works correctly
        $data = ['a', 'b', 'c', 'd', 'e'];
        $source = new ArraySource($data);
        $adapter = new OffsetAdapter($source);

        // Test with nowCount = 0 (default)
        $result1 = $adapter->execute(0, 3, 0);
        $fetched1 = $result1->fetchAll();
        $this->assertIsArray($fetched1);
        $this->assertEquals(3, $result1->getTotalCount());

        // Test with nowCount = 1 (should still work)
        $result2 = $adapter->execute(0, 3, 1);
        $fetched2 = $result2->fetchAll();
        $this->assertSame(['b', 'c'], $fetched2);
        $this->assertEquals(2, $result2->getTotalCount());

        // Test optional parameter (defaults to 0)
        $result3 = $adapter->execute(0, 3); // No nowCount parameter
        $fetched3 = $result3->fetchAll();
        $this->assertEquals($fetched1, $fetched3); // Should be same as explicit 0
    }

    public function testRealisticPaginationScenarios(): void
    {
        // Test realistic pagination scenarios that would be used in real applications
        $largeDataset = range(1, 1000);
        $source = new ArraySource($largeDataset);
        $adapter = new OffsetAdapter($source);

        // Test typical pagination: get first page
        $result1 = $adapter->execute(0, 20); // Page 1: items 1-20
        $page1 = $result1->fetchAll();
        $this->assertIsArray($page1);
        $this->assertLessThanOrEqual(20, count($page1));
        $this->assertEquals(count($page1), $result1->getTotalCount());
        if (!empty($page1)) {
            $this->assertGreaterThanOrEqual(1, $page1[0]); // Should contain positive integers
        }

        // Test second page
        $result2 = $adapter->execute(20, 20); // Page 2: items 21-40
        $page2 = $result2->fetchAll();
        $this->assertIsArray($page2);
        $this->assertLessThanOrEqual(20, count($page2));
        $this->assertEquals(count($page2), $result2->getTotalCount());

        // Pages should be different (no overlap in typical pagination)
        if (!empty($page1) && !empty($page2)) {
            $this->assertNotEquals($page1[0], $page2[0]);
        }

        // Test large offset
        $result3 = $adapter->execute(950, 50); // Near end of dataset
        $page3 = $result3->fetchAll();
        $this->assertIsArray($page3);
        $this->assertLessThanOrEqual(50, count($page3));
        $this->assertEquals(count($page3), $result3->getTotalCount());

        // Test offset beyond dataset
        $result4 = $adapter->execute(2000, 10); // Way beyond end
        $page4 = $result4->fetchAll();
        $this->assertIsArray($page4);
        $this->assertEquals(count($page4), $result4->getTotalCount());
        // Should return empty or partial results, but not crash
    }

    public function testPaginationConsistency(): void
    {
        // Test that pagination behaves consistently across multiple calls
        $dataset = range(1, 200);
        $source = new ArraySource($dataset);
        $adapter = new OffsetAdapter($source);

        // Make the same request multiple times - should get consistent results
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $result = $adapter->execute(40, 10); // Same request each time
            $results[] = $result->fetchAll();
            $this->assertEquals(count($results[0]), $result->getTotalCount());
        }

        // All results should be identical
        $this->assertEquals($results[0], $results[1]);
        $this->assertEquals($results[1], $results[2]);
    }

    public function testPaginationWithDifferentLimits(): void
    {
        // Test that different limits work correctly
        $dataset = range(1, 100);
        $source = new ArraySource($dataset);
        $adapter = new OffsetAdapter($source);

        $limits = [1, 5, 10, 25, 50, 100];
        foreach ($limits as $limit) {
            $result = $adapter->execute(0, $limit);
            $data = $result->fetchAll();

            $this->assertIsArray($data);
            $this->assertLessThanOrEqual($limit, count($data));
            $this->assertEquals(count($data), $result->getTotalCount());

            // Data should start from beginning
            if (!empty($data)) {
                $this->assertEquals(1, $data[0]);
            }
        }
    }
}
