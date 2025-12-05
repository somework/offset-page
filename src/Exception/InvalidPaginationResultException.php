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
 * Exception thrown when pagination results are invalid or unexpected.
 *
 * This exception is used when the pagination system receives invalid data
 * from sources or when internal validation fails.
 */
class InvalidPaginationResultException extends \UnexpectedValueException implements PaginationExceptionInterface
{
    /**
     * Create an exception for invalid callback result type.
     *
     * @param mixed  $result       The invalid result from callback
     * @param string $expectedType The expected type
     * @param string $context      Additional context about where this occurred
     *
     * @return self
     */
    public static function forInvalidCallbackResult(mixed $result, string $expectedType, string $context = ''): self
    {
        $actualType = get_debug_type($result);
        /** @noinspection SpellCheckingInspection */
        $message = sprintf(
            'Callback %smust return %s, got %s',
            $context ? "($context) " : '',
            $expectedType,
            $actualType,
        );

        return new self($message);
    }
}
