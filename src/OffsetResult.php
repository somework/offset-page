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
    private int $fetchedCount = 0;
    private \Generator $generator;

    /**
     * @param \Generator<\Generator<T>> $sourceResultGenerator
     */
    public function __construct(\Generator $sourceResultGenerator)
    {
        $this->generator = $this->execute($sourceResultGenerator);
    }

    /**
     * @return OffsetResult<T>
     */
    public static function empty(): self
    {
        /** @return \Generator<\Generator<never-return>> */
        $emptyGenerator = static fn () => yield from [];
        return new self($emptyGenerator());
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
     *
     * @throws InvalidPaginationResultException
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

    /**
     * @return \Generator<T>
     */
    public function generator(): \Generator
    {
        return $this->generator;
    }

    public function getFetchedCount(): int
    {
        return $this->fetchedCount;
    }

    /**
     * @param \Generator<\Generator<T>> $generator
     *
     * @return \Generator<T>
     */
    protected function execute(\Generator $generator): \Generator
    {
        foreach ($generator as $pageGenerator) {
            foreach ($pageGenerator as $result) {
                $this->fetchedCount++;
                yield $result;
            }
        }
    }
}
