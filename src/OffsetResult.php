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
        // Collect all source results and calculate total count
        $sourceResults = [];
        foreach ($sourceResultGenerator as $sourceResult) {
            if (!is_object($sourceResult) || !($sourceResult instanceof SourceResultInterface)) {
                throw new \UnexpectedValueException(sprintf(
                    'Result of generator is not an instance of %s',
                    SourceResultInterface::class,
                ));
            }

            $sourceResults[] = $sourceResult;

            $sourceCount = $sourceResult->getTotalCount();
            if ($sourceCount > $this->totalCount) {
                $this->totalCount = $sourceCount;
            }
        }

        // Create generator from collected sources
        $this->generator = $this->executeFromSources($sourceResults);
        // Prime the generator to ensure it's in the correct state
        if ($this->generator->valid()) {
            // Don't advance, just ensure the generator is started
        }
    }

    /**
     * @return T|null
     */
    public function fetch(): mixed
    {
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();

            return $value;
        }

        return null; // End of data
    }

    /**
     * @return array<T>
     */
    public function fetchAll(): array
    {
        $result = [];
        while ($this->generator->valid()) {
            $result[] = $this->generator->current();
            $this->generator->next();
        }

        return $result;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * @param SourceResultInterface<T>[] $sourceResults
     */
    private function executeFromSources(array $sourceResults): \Generator
    {
        foreach ($sourceResults as $sourceResult) {
            foreach ($sourceResult->generator() as $result) {
                yield $result;
            }
        }
    }
}
