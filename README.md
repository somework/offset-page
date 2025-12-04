# Offset Page

[![CI](https://img.shields.io/github/actions/workflow/status/somework/offset-page/ci.yml?branch=master&label=CI)](https://github.com/somework/offset-page/actions/workflows/ci.yml?query=branch%3Amaster)
[![Latest Stable Version](https://img.shields.io/packagist/v/somework/offset-page.svg)](https://packagist.org/packages/somework/offset-page)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/somework/offset-page.svg)](https://packagist.org/packages/somework/offset-page)

**Offset source adapter for PHP 8.2+**

This library provides an adapter to fetch items from data sources that only support page-based pagination, converting offset-based requests to page-based requests internally.

## Requirements

- PHP 8.2 or higher
- [somework/offset-page-logic](https://github.com/somework/offset-page-logic) ^2.0

## Installation

```bash
composer require somework/offset-page
```

## Usage

### Basic Example

```php
use SomeWork\OffsetPage\OffsetAdapter;
use SomeWork\OffsetPage\SourceCallbackAdapter;

// Create a source that returns page-based results
$source = new SourceCallbackAdapter(function (int $page, int $pageSize) {
    // Your page-based API call here
    // For example, fetching from a database with LIMIT/OFFSET
    $data = fetchFromDatabase($page, $pageSize);

    return new MySourceResult($data, $totalCount);
});

// Create the offset adapter
$adapter = new OffsetAdapter($source);

// Fetch items 50-99 (offset 50, limit 50)
$result = $adapter->execute(50, 50);

// Get all fetched items
$items = $result->fetchAll();

// Or fetch items one by one
while (($item = $result->fetch()) !== null) {
    // Process $item
}

// Get total count of available items
$totalCount = $result->getTotalCount();
```

### Implementing SourceResultInterface

Your data source must return objects that implement `SourceResultInterface`:

```php
use SomeWork\OffsetPage\SourceResultInterface;

class MySourceResult implements SourceResultInterface
{
    public function __construct(
        private array $data,
        private int $totalCount
    ) {}

    public function generator(): \Generator
    {
        foreach ($this->data as $item) {
            yield $item;
        }
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }
}
```

### Using Custom Source Classes

You can also implement `SourceInterface` directly:

```php
use SomeWork\OffsetPage\SourceInterface;
use SomeWork\OffsetPage\SourceResultInterface;

class MyApiSource implements SourceInterface
{
    public function execute(int $page, int $pageSize): SourceResultInterface
    {
        // Fetch data from your API using pages
        $response = $this->apiClient->getItems($page, $pageSize);

        return new MySourceResult($response->data, $response->totalCount);
    }
}

$adapter = new OffsetAdapter(new MyApiSource());
$result = $adapter->execute(100, 25);
```

## How It Works

This library uses [somework/offset-page-logic](https://github.com/somework/offset-page-logic) internally to convert offset-based requests to page-based requests. When you request items with an offset and limit, the library:

1. Calculates which pages need to be fetched
2. Calls your source for each required page
3. Combines the results into a single offset-based result

This is particularly useful when working with APIs or databases that only support page-based pagination but your application logic requires offset-based access.

## Development

### Available Scripts

This project includes several composer scripts for development and quality assurance:

```bash
composer test          # Run PHPUnit tests
composer stan          # Run PHPStan static analysis
composer cs-check      # Check code style with PHP-CS-Fixer
composer cs-fix         # Fix code style issues with PHP-CS-Fixer
composer quality        # Run static analysis and code style checks
```

### Testing

The library includes comprehensive tests covering:
- Unit tests for all core classes
- Integration tests for real-world scenarios
- Property-based tests for edge cases
- Memory usage and performance tests

## Author

[Igor Pinchuk](https://github.com/somework) - <i.pinchuk.work@gmail.com>

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
