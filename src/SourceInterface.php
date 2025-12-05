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

namespace SomeWork\OffsetPage;

/**
 * Data source interface for pagination operations.
 *
 * Implementations provide access to paginated data by accepting page-based requests
 * and returning generators that yield the requested items.
 *
 * @template T
 */
interface SourceInterface
{
    /**
     * Execute a pagination request and return data generator.
     *
     * This method is called by the pagination adapter to retrieve data for a specific page.
     * The implementation should:
     *
     * - Accept 1-based page numbers (page 1 = first page, page 2 = second page, etc.)
     * - Accept pageSize indicating maximum items to return for this page
     * - Return a Generator that yields items of type T
     * - Handle pageSize = 0 by returning an empty generator
     * - Handle page < 1 gracefully (typically treat as page 1 or return empty)
     * - Be stateless - multiple calls with same parameters should return identical results
     * - Return empty generator when no more data exists for the requested page
     *
     * The generator will be consumed eagerly by the pagination system. If the generator
     * yields fewer items than requested, it signals the end of available data.
     *
     * @param positive-int $page 1-based page number (≥1 for valid requests)
     * @param positive-int $pageSize Maximum number of items to return (≥0, 0 means no items)
     *
     * @return \Generator<T> Generator yielding items for the requested page
     *
     * @throws \Throwable Any implementation-specific errors should be thrown directly
     */
    public function execute(int $page, int $pageSize): \Generator;
}
