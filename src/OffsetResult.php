<?php

/*
 * This file is part of the SomeWork/OffsetPage package.
 *
 * (c) Pinchuk Igor <i.pinchuk.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SomeWork\OffsetPage;

class OffsetResult
{
    /**
     * @var int
     */
    protected $totalCount = 0;

    /**
     * @var \Generator
     */
    protected $generator;

    /**
     * OffsetResult constructor.
     *
     * @param \Generator $sourceResultGenerator
     *
     * @throws \UnexpectedValueException
     */
    public function __construct(\Generator $sourceResultGenerator)
    {
        $this->generator = $this->execute($sourceResultGenerator);
        if ($this->generator->valid()) {
            $this->generator->current();
        }
    }

    /**
     * @throws \UnexpectedValueException
     *
     * @return mixed|null
     */
    public function fetch()
    {
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();

            return $value;
        }
    }

    /**
     * @throws \UnexpectedValueException
     *
     * @return array
     */
    public function fetchAll()
    {
        $result = [];
        while (($data = $this->fetch()) || $data !== null) {
            $result[] = $data;
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * @param \Generator $generator
     *
     * @throws \UnexpectedValueException
     *
     * @return \Generator
     */
    protected function execute(\Generator $generator)
    {
        foreach ($generator as $sourceResult) {
            if (!is_object($sourceResult) || !($sourceResult instanceof SourceResultInterface)) {
                throw new \UnexpectedValueException(sprintf(
                    'Result of generator is not an instance of %s',
                    SourceResultInterface::class
                ));
            }

            $sourceCount = $sourceResult->getTotalCount();
            if ($sourceCount > $this->totalCount) {
                $this->totalCount = $sourceCount;
            }

            foreach ($sourceResult->generator() as $result) {
                yield $result;
            }
        }
    }
}
