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

namespace SomeWork\OffsetPage\Exception;

/**
 * Interface for all pagination-related exceptions.
 *
 * This interface allows developers to catch all pagination exceptions
 * by type-hinting against this interface, providing better error handling
 * and type safety for pagination operations.
 *
 * By extending \Throwable, this interface ensures that implementing classes
 * are proper exceptions that can be thrown and caught.
 */
interface PaginationExceptionInterface extends \Throwable
{
}
