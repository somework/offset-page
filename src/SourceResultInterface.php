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

interface SourceResultInterface
{
    /**
     * @return \Generator
     */
    public function generator();

    /**
     * @return int
     */
    public function getTotalCount();
}
