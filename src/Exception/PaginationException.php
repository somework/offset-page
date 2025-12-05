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
 * Base exception for all pagination-related errors.
 *
 * This class serves as a foundation for more specific pagination exceptions
 * and implements the PaginationExceptionInterface for consistent error handling.
 */
class PaginationException extends \RuntimeException implements PaginationExceptionInterface
{
}
