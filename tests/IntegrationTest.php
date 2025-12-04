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

class IntegrationTest extends TestCase
{
    public function testFullWorkflowWithArraySource(): void
    {
        $data = range(1, 100); // 100 items
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);

        // Test pagination through the entire dataset
        $allResults = [];
        $offset = 0;
        $limit = 10;

        while (true) {
            $result = $adapter->execute($offset, $limit);
            $batch = $result->fetchAll();

            if (empty($batch)) {
                break;
            }

            $allResults = array_merge($allResults, $batch);
            $offset += $limit;

            if (count($batch) < $limit) {
                break; // Last batch
            }
        }

        $this->assertEquals($data, $allResults);
    }

    public function testRealWorldPaginationScenario(): void
    {
        // Simulate a database with 47 total records, page size 10
        $totalRecords = 47;
        $pageSize = 10;

        $callLog = [];
        $source = new SourceCallbackAdapter(function (int $page, int $pageSize) use (&$callLog, $totalRecords) {
            $callLog[] = ['page' => $page, 'size' => $pageSize];

            // Simulate database pagination
            $startIndex = ($page - 1) * $pageSize;
            $endIndex = min($startIndex + $pageSize, $totalRecords);

            $pageData = [];
            for ($i = $startIndex; $i < $endIndex; $i++) {
                $pageData[] = 'record_'.($i + 1);
            }

            return new ArraySourceResult($pageData, $totalRecords);
        });

        $adapter = new OffsetAdapter($source);

        // Request first 15 records (offset 0, limit 15)
        $result = $adapter->execute(0, 15);
        $records = $result->fetchAll();

        $this->assertLessThanOrEqual(15, count($records)); // May get fewer due to pagination logic
        $this->assertEquals(47, $result->getTotalCount());
        if (!empty($records)) {
            $this->assertEquals('record_1', $records[0]);
        }

        // Should have made at least 1 API call
        $this->assertNotEmpty($callLog);
    }

    public function testOffsetBeyondAvailableData(): void
    {
        $data = range(1, 50);
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);

        // Request data starting at offset 100 (beyond available data)
        $result = $adapter->execute(100, 10);

        $this->assertEquals([], $result->fetchAll());
        $this->assertEquals(0, $result->getTotalCount()); // Page count for empty results
    }

    public function testPartialPageRequests(): void
    {
        $data = range(1, 25); // 25 items
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);

        // Request items 10-14 (offset 10, limit 5)
        $result = $adapter->execute(10, 5);
        $records = $result->fetchAll();

        $this->assertEquals([11, 12, 13, 14, 15], $records);
        $this->assertEquals(5, $result->getTotalCount());
    }

    public function testLargeOffsetWithSmallLimit(): void
    {
        $data = range(1, 1000);
        $source = new ArraySource($data);

        $adapter = new OffsetAdapter($source);

        // Request single item at large offset
        $result = $adapter->execute(999, 1);
        $records = $result->fetchAll();

        $this->assertEquals([1000], $records);
        $this->assertEquals(1, $result->getTotalCount());
    }

    public function testApiFailureSimulation(): void
    {
        $callCount = 0;
        $source = new SourceCallbackAdapter(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 2) {
                throw new \RuntimeException('API temporarily unavailable');
            }

            return new ArraySourceResult(['success'], 1);
        });

        $adapter = new OffsetAdapter($source);

        // First call should succeed
        $result1 = $adapter->execute(0, 1);
        $this->assertEquals(['success'], $result1->fetchAll());

        // Second call should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API temporarily unavailable');
        $adapter->execute(1, 1);
    }

    public function testMemoryUsageWithLargeDatasets(): void
    {
        // Create a large dataset
        $largeData = [];
        for ($i = 0; $i < 10000; $i++) {
            $largeData[] = 'item_'.$i;
        }

        $source = new ArraySource($largeData);
        $adapter = new OffsetAdapter($source);

        $memoryBefore = memory_get_usage();

        // Process in small batches to test memory efficiency
        $processed = 0;
        $offset = 0;
        $batchSize = 100;

        while ($processed < count($largeData)) {
            $result = $adapter->execute($offset, $batchSize);
            $batch = $result->fetchAll();
            $processed += count($batch);
            $offset += $batchSize;

            // Check memory usage periodically
            if ($processed % 1000 === 0) {
                $memoryNow = memory_get_usage();
                // Allow reasonable memory growth but not excessive
                $this->assertLessThan($memoryBefore + 1024 * 1024 * 5, $memoryNow); // Max 5MB increase
            }

            if (count($batch) < $batchSize) {
                break;
            }
        }

        $this->assertEquals(10000, $processed);
    }

    public function testConcurrentAccessSimulation(): void
    {
        $sharedData = range(1, 100);
        $accessLog = [];

        $source = new SourceCallbackAdapter(function (int $page, int $size) use ($sharedData, &$accessLog) {
            $accessLog[] = ['page' => $page, 'size' => $size, 'time' => microtime(true)];

            $startIndex = ($page - 1) * $size;

            $pageData = array_slice($sharedData, $startIndex, $size);

            return new ArraySourceResult($pageData, count($sharedData));
        });

        $adapter = new OffsetAdapter($source);

        // Simulate multiple requests
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $result = $adapter->execute($i * 10, 10);
            $results[] = $result->fetchAll();
        }

        // Verify all results are correct
        $this->assertCount(5, $results);
        $this->assertEquals(range(1, 10), $results[0]);
        $this->assertEquals(range(41, 50), $results[4]);

        // Verify API was called for each request
        $this->assertCount(5, $accessLog);
    }

    #[DataProvider('paginationScenariosProvider')]
    public function testVariousPaginationScenarios(
        array $data,
        int $offset,
        int $limit,
        array $expectedResults,
        int $expectedTotalCount,
    ): void {
        $source = new ArraySource($data);
        $adapter = new OffsetAdapter($source);

        $result = $adapter->execute($offset, $limit);
        $actualResults = $result->fetchAll();

        $this->assertEquals($expectedResults, $actualResults);
        $this->assertEquals($expectedTotalCount, $result->getTotalCount());
    }

    public static function paginationScenariosProvider(): array
    {
        return [
            'empty_dataset' => [
                [],
                0,
                10,
                [],
                0,
            ],
            'single_item_dataset' => [
                ['item1'],
                0,
                10,
                ['item1'],
                1,
            ],
            'exact_page_size' => [
                range(1, 10),
                0,
                10,
                range(1, 10),
                10,
            ],
            'partial_last_page' => [
                range(1, 25),
                20,
                10,
                range(21, 25),
                5,
            ],
            'offset_at_end' => [
                range(1, 10),
                10,
                5,
                [],
                0,
            ],
            'offset_beyond_end' => [
                range(1, 5),
                10,
                5,
                [],
                0,
            ],
            'zero_limit' => [
                range(1, 10),
                0,
                0,
                [],
                0,
            ],
            'large_limit' => [
                range(1, 50),
                0,
                100,
                range(1, 50),
                50,
            ],
            'mixed_data_types' => [
                [1, 'string', 3.14, true, null],
                0,
                3,
                [1, 'string', 3.14],
                3,
            ],
        ];
    }

    public function testStreamingProcessing(): void
    {
        $data = range(1, 100);
        $source = new ArraySource($data);
        $adapter = new OffsetAdapter($source);

        $result = $adapter->execute(0, 100);

        // Simulate streaming processing - don't load all into memory at once
        $processed = [];
        $count = 0;

        while (($item = $result->fetch()) !== null) {
            $processed[] = $item * 2; // Some processing
            $count++;

            // Simulate breaking early
            if ($count >= 10) {
                break;
            }
        }

        $this->assertCount(10, $processed);
        $this->assertEquals([2, 4, 6, 8, 10, 12, 14, 16, 18, 20], $processed);
    }

    public function testErrorRecoveryScenario(): void
    {
        $failureCount = 0;
        $source = new SourceCallbackAdapter(function () use (&$failureCount) {
            $failureCount++;
            if ($failureCount <= 2) {
                throw new \RuntimeException("Temporary failure #{$failureCount}");
            }

            return new ArraySourceResult(['success'], 1);
        });

        $adapter = new OffsetAdapter($source);

        // First two calls should fail
        try {
            $adapter->execute(0, 1);
            $this->fail('Expected exception on first call');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Temporary failure #1', $e->getMessage());
        }

        try {
            $adapter->execute(0, 1);
            $this->fail('Expected exception on second call');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Temporary failure #2', $e->getMessage());
        }

        // Third call should succeed
        $result = $adapter->execute(0, 1);
        $this->assertEquals(['success'], $result->fetchAll());
    }

    public function testNowCountIntegration(): void
    {
        // Test nowCount with SourceCallbackAdapter
        $source = new SourceCallbackAdapter(function (int $page, int $size) {
            $data = array_fill(0, min($size, 5), "item_$page");

            return new ArraySourceResult($data, 50);
        });

        $adapter = new OffsetAdapter($source);

        // Test with different nowCount values
        $result1 = $adapter->execute(0, 5, 0);
        $result2 = $adapter->execute(0, 5, 2);

        $this->assertIsArray($result1->fetchAll());
        $this->assertIsArray($result2->fetchAll());
        $this->assertEquals(50, $result1->getTotalCount());
        $this->assertEquals(50, $result2->getTotalCount());
    }

    public function testRealWorldApiIntegration(): void
    {
        // Simulate a real API that returns paginated data
        $apiCallCount = 0;
        $source = new SourceCallbackAdapter(function (int $page, int $size) use (&$apiCallCount) {
            $apiCallCount++;

            // Simulate different page sizes and data based on page
            $totalItems = 87; // Odd number to test edge cases
            $startIndex = ($page - 1) * $size;

            if ($startIndex >= $totalItems) {
                return new ArraySourceResult([], $totalItems);
            }

            $endIndex = min($startIndex + $size, $totalItems);
            $pageData = [];
            for ($i = $startIndex; $i < $endIndex; $i++) {
                $pageData[] = 'record_'.($i + 1);
            }

            return new ArraySourceResult($pageData, $totalItems);
        });

        $adapter = new OffsetAdapter($source);

        // Test typical API usage patterns
        $testScenarios = [
            [0, 10, 'first_page'],
            [10, 10, 'second_page'],
            [20, 10, 'third_page'],
            [80, 10, 'last_page'],
            [90, 10, 'beyond_end'],
        ];

        foreach ($testScenarios as [$offset, $limit, $description]) {
            $initialCallCount = $apiCallCount;
            $result = $adapter->execute($offset, $limit);
            $data = $result->fetchAll();

            // Verify basic properties
            $this->assertIsArray($data, "Failed for $description");
            $this->assertLessThanOrEqual($limit, count($data), "Failed for $description");
            $this->assertEquals(87, $result->getTotalCount(), "Failed for $description");

            // Verify API was called (at least once per request)
            $this->assertGreaterThan($initialCallCount, $apiCallCount, "API not called for $description");

            // Verify data consistency
            if (!empty($data)) {
                $this->assertStringStartsWith('record_', $data[0], "Invalid data format for $description");
            }
        }

        // Verify reasonable API call efficiency (shouldn't make excessive calls)
        $this->assertLessThan(20, $apiCallCount, 'Too many API calls made');
    }

    public function testLargeDatasetHandling(): void
    {
        // Test with a reasonably large dataset to ensure memory efficiency
        $largeDataset = range(1, 1000);
        $source = new ArraySource($largeDataset);
        $adapter = new OffsetAdapter($source);

        // Test various access patterns
        $patterns = [
            [0, 100],    // First 100 items
            [500, 50],   // Middle section
            [950, 100],  // End section (will be truncated)
        ];

        foreach ($patterns as [$offset, $limit]) {
            $result = $adapter->execute($offset, $limit);
            $data = $result->fetchAll();

            $this->assertIsArray($data);
            $this->assertLessThanOrEqual($limit, count($data));
            $this->assertEquals(count($data), $result->getTotalCount());

            // Verify data is in expected range
            if (!empty($data)) {
                $this->assertGreaterThanOrEqual($offset + 1, $data[0]);
                $this->assertLessThanOrEqual($offset + $limit, end($data));
            }
        }
    }
}
