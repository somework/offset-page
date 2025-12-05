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
     * Create a new InvalidPaginationArgumentException containing the parameter values that caused the error.
     *
     * @param array<string, mixed> $parameters Associative map of parameter names to the values that triggered the exception.
     * @param string               $message    Human-readable error message.
     * @param int                  $code       Optional error code.
     * @param \Throwable|null      $previous   Optional previous exception for chaining.
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
     * Create an exception representing a single invalid pagination parameter.
     *
     * @param string $parameterName The name of the invalid parameter.
     * @param mixed  $value         The provided value for the parameter.
     * @param string $description   Short description of what the parameter represents.
     *
     * @return self An exception containing the invalid parameter and a message describing the expected value.
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
     * Create an exception describing an invalid combination where `limit` is zero but `offset` or `nowCount` are non-zero.
     *
     * @param int $offset   The pagination offset that was provided.
     * @param int $limit    The pagination limit value (expected to be zero in this check).
     * @param int $nowCount The current count of items already paginated.
     *
     * @return self An exception instance containing the keys `offset`, `limit`, and `nowCount` in its parameters.
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
     * Retrieve the value for a named parameter stored on the exception.
     *
     * @param string $name Name of the parameter to retrieve.
     *
     * @return mixed The parameter's value if set, or null if not present.
     */
    public function getParameter(string $name): mixed
    {
        return array_key_exists($name, $this->parameters)
            ? $this->parameters[$name]
            : null;
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
