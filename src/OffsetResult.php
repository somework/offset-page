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
        $this->generator = $this->execute($sourceResultGenerator);
        if ($this->generator->valid()) {
            $this->generator->current();
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
     *
     * @throws \UnexpectedValueException
     */
    public function fetchAll(): array
    {
        $result = [];
        while (null !== ($data = $this->fetch())) {
            $result[] = $data;
        }

        return $result;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * @throws \UnexpectedValueException
     */
    protected function execute(\Generator $generator): \Generator
    {
        foreach ($generator as $sourceResult) {
            if (!is_object($sourceResult) || !($sourceResult instanceof SourceResultInterface)) {
                throw new \UnexpectedValueException(sprintf(
                    'Result of generator is not an instance of %s',
                    SourceResultInterface::class,
                ));
            }

            $sourceCount = $sourceResult->getResultCount();
            $this->totalCount += $sourceCount;

            foreach ($sourceResult->generator() as $result) {
                yield $result;
            }
        }
    }
}
