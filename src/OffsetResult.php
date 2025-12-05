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

use SomeWork\OffsetPage\Exception\InvalidPaginationResultException;

/**
 * @template T
 */
class OffsetResult
{
    private int $totalCount = 0;
    private \Generator $generator;

    /**
     * @param \Generator<SourceResultInterface<T>> $sourceResultGenerator
     */
    public function __construct(\Generator $sourceResultGenerator)
    {
        $this->generator = $this->execute($sourceResultGenerator);
    }

    /**
     * @return T|null
     */
    public function fetch()
    {
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();

            return $value;
        }

        return null; // End of data
    }

    /**
     * @throws InvalidPaginationResultException
     *
     * @return array<T>
     */
    public function fetchAll(): array
    {
        $result = [];
        while ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();
            $result[] = $value;
        }

        return $result;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * @throws InvalidPaginationResultException
     */
    protected function execute(\Generator $generator): \Generator
    {
        foreach ($generator as $sourceResult) {
            if (!is_object($sourceResult) || !($sourceResult instanceof SourceResultInterface)) {
                throw InvalidPaginationResultException::forInvalidSourceResult(
                    $sourceResult,
                    SourceResultInterface::class,
                );
            }

            foreach ($sourceResult->generator() as $result) {
                $this->totalCount++;
                yield $result;
            }
        }
    }
}
