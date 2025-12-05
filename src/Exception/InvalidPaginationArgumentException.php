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
 * Exception thrown when pagination arguments are invalid.
 *
 * Provides detailed information about which parameters were invalid
 * and their values, along with suggestions for correction.
 */
class InvalidPaginationArgumentException extends \InvalidArgumentException implements PaginationExceptionInterface
{
    /** @var array<string, mixed> */
    private array $parameters;

    /**
     * @param array<string, mixed> $parameters The parameter values that were provided
     * @param string               $message    The error message
     * @param int                  $code       The error code (optional)
     * @param \Throwable|null      $previous   The previous exception (optional)
     */
    public function __construct(
        array $parameters,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->parameters = $parameters;
    }

    /**
     * Create an exception for invalid parameter values.
     *
     * @param string $parameterName The name of the invalid parameter
     * @param mixed  $value         The invalid value
     * @param string $description   Description of what the parameter represents
     *
     * @return self
     */
    public static function forInvalidParameter(
        string $parameterName,
        mixed $value,
        string $description,
    ): self {
        $parameters = [$parameterName => $value];

        $message = sprintf(
            '%s must be greater than or equal to zero, got %s. Use a non-negative integer to specify the %s.',
            $parameterName,
            is_scalar($value) ? $value : gettype($value),
            $description,
        );

        return new self($parameters, $message);
    }

    /**
     * Create an exception for invalid zero limit combinations.
     *
     * @param int $offset   The offset value
     * @param int $limit    The limit value (should be 0)
     * @param int $nowCount The nowCount value
     *
     * @return self
     */
    public static function forInvalidZeroLimit(int $offset, int $limit, int $nowCount): self
    {
        $parameters = [
            'offset'   => $offset,
            'limit'    => $limit,
            'nowCount' => $nowCount,
        ];

        $message = sprintf(
            'Zero limit is only allowed when both offset and nowCount are also zero (current: offset=%d, limit=%d, nowCount=%d). '.
            'Zero limit indicates "fetch all remaining items" and can only be used at the start of pagination. '.
            'For unlimited fetching, use a very large limit value instead.',
            $offset,
            $limit,
            $nowCount,
        );

        return new self($parameters, $message);
    }

    /**
     * Get a specific parameter value.
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value, or null if not set
     */
    public function getParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * Get the parameter values that caused this exception.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
