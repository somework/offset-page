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

use SomeWork\OffsetPage\Logic\AlreadyGetNeededCountException;
use SomeWork\OffsetPage\Logic\Offset;

class OffsetAdapter
{
    /**
     * @var SourceInterface
     */
    protected $source;

    public function __construct(SourceInterface $source)
    {
        $this->source = $source;
    }

    /**
     * @param int $offset
     * @param int $limit
     * @param int $nowCount
     *
     * @throws \LogicException
     *
     * @return \SomeWork\OffsetPage\OffsetResult
     */
    public function execute($offset, $limit, $nowCount = 0)
    {
        return new OffsetResult($this->logic($offset, $limit, $nowCount));
    }

    /**
     * @param $offset
     * @param $limit
     * @param $nowCount
     *
     * @throws \LogicException
     *
     * @return \Generator
     */
    protected function logic($offset, $limit, $nowCount)
    {
        try {
            while ($offsetResult = Offset::logic($offset, $limit, $nowCount)) {
                $result = $this->source->execute($offsetResult->getPage(), $offsetResult->getSize());
                if ($result->getTotalCount() === 0) {
                    return;
                }
                $nowCount += $result->getTotalCount();
                yield $result;
            }
        } catch (AlreadyGetNeededCountException $exception) {
            return;
        }
    }
}
