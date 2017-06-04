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
     * @var \Generator
     */
    protected $sourceResultGenerator;

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
     */
    public function __construct(\Generator $sourceResultGenerator)
    {
        $this->totalCount = 0;
        $this->sourceResultGenerator = $sourceResultGenerator;
    }

    /**
     * @return mixed|null
     * @throws \InvalidArgumentException
     */
    public function fetch()
    {
        if (!$this->generator) {
            $this->generator = $this->execute();
        }
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();
            return $value;
        }
        return null;
    }

    /**
     * @return array
     * @throws \InvalidArgumentException
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
     * @return \Generator
     * @throws \InvalidArgumentException
     */
    protected function execute()
    {
        while ($sourceResult = $this->getSourceResult()) {
            if (!is_object($sourceResult) || !($sourceResult instanceof SourceResultInterface)) {
                throw new \InvalidArgumentException(sprintf(
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

    protected function getSourceResult()
    {
        if ($this->sourceResultGenerator->valid()) {
            $value = $this->sourceResultGenerator->current();
            $this->sourceResultGenerator->next();
            return $value;
        }
        return null;
    }
}
