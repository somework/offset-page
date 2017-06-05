<?php

/*
 * This file is part of the SomeWork/OffsetPage package.
 *
 * (c) Pinchuk Igor <i.pinchuk.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SomeWork\OffsetPage\Tests;

use PHPUnit\Framework\TestCase;
use SomeWork\OffsetPage\OffsetAdapter;
use SomeWork\OffsetPage\OffsetResult;
use SomeWork\OffsetPage\SourceCallbackAdapter;
use SomeWork\OffsetPage\SourceResultInterface;

class OffsetResultTest extends TestCase
{
    public function testNotSourceResultInterfaceGenerator()
    {
        $this->setExpectedException(\UnexpectedValueException::class);
        $notSourceResultGeneratorFunction = function () {
            yield 1;
        };

        $offsetResult = new OffsetResult($notSourceResultGeneratorFunction());
        $offsetResult->fetch();
    }

    public function testTotalCount()
    {
        $sourceResult = $this
            ->getMockBuilder(SourceResultInterface::class)
            ->setMethods(['getTotalCount', 'generator'])
            ->getMock();

        $sourceResult
            ->expects($this->exactly(1))
            ->method('getTotalCount')
            ->willReturn(10);

        $sourceResult
            ->method('generator')
            ->willReturn($this->getGenerator(['test']));

        $offsetResult = new OffsetResult($this->getGenerator([$sourceResult]));
        $this->assertEquals(10, $offsetResult->getTotalCount());
        $offsetResult->fetchAll();

        $this->assertEquals(10, $offsetResult->getTotalCount());
    }

    /**
     * @param $value
     *
     * @return \Generator
     */
    protected function getGenerator(array $value)
    {
        foreach ($value as $item) {
            yield $item;
        }
    }

    /**
     * @param array $totalCountValues
     * @param int   $expectsCount
     *
     * @dataProvider totalCountProvider
     */
    public function testTotalCountNotChanged(array $totalCountValues, $expectsCount)
    {
        $sourceResult = $this
            ->getMockBuilder(SourceResultInterface::class)
            ->setMethods(['getTotalCount', 'generator'])
            ->getMock();

        $sourceResultArray = [];
        foreach ($totalCountValues as $totalCountValue) {
            $clone = clone $sourceResult;
            $clone
                ->method('generator')
                ->willReturn($this->getGenerator([$totalCountValue]));
            $clone
                ->method('getTotalCount')
                ->willReturn($totalCountValue);
            $sourceResultArray[] = $clone;
        }

        $offsetResult = new OffsetResult($this->getGenerator($sourceResultArray));
        $offsetResult->fetchAll();
        $this->assertEquals($expectsCount, $offsetResult->getTotalCount());
    }

    /**
     * @return array
     */
    public function totalCountProvider()
    {
        return [
            [
                [8, 9, 10],
                10,
            ],
            [
                [],
                0,
            ],
            [
                [20, 0, 10],
                20,
            ],
            [
                [-1, -10],
                0,
            ],
        ];
    }

    /**
     * @param SourceResultInterface[] $sources
     * @param array                   $expectedResult
     *
     * @dataProvider fetchedCountProvider
     */
    public function testFetchedCount(array $sources, $expectedResult)
    {
        $offsetResult = new OffsetResult($this->getGenerator($sources));
        $this->assertEquals($expectedResult, $offsetResult->fetchAll());
    }

    /**
     * @return array
     */
    public function fetchedCountProvider()
    {
        return [
            [

                'sources'        => [
                    new ArraySourceResult([0], 3),
                    new ArraySourceResult([1], 3),
                    new ArraySourceResult([2], 3),
                ],
                'expectedResult' => [0, 1, 2],
            ],
        ];
    }

    /**
     * Infinite fetch
     */
    public function testError()
    {
        $callback = function () {
            return new ArraySourceResult([1], 1);
        };

        $offsetAdapter = new OffsetAdapter(new SourceCallbackAdapter($callback));
        $result = $offsetAdapter->execute(0, 0);

        $this->assertEquals(1, $result->getTotalCount());
        $this->assertEquals([1], $result->fetchAll());
    }
}
