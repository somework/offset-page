# Offset Page

[![CI](https://img.shields.io/github/actions/workflow/status/somework/offset-page/ci.yml?branch=master&label=CI)](https://github.com/somework/offset-page/actions/workflows/ci.yml?query=branch%3Amaster)
[![Latest Stable Version](https://img.shields.io/packagist/v/somework/offset-page.svg)](https://packagist.org/packages/somework/offset-page)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/somework/offset-page.svg)](https://packagist.org/packages/somework/offset-page)

**Offset source adapter for PHP 8.2+ with comprehensive exception handling**

This library provides an adapter to fetch items from data sources that only support page-based pagination, converting
offset-based requests to page-based requests internally. Features comprehensive exception architecture and advanced
pagination controls.

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
$source = new SourceCallbackAdapter(function (int $page, int $pageSize): \Generator {
    // Your page-based API call here
    // For example, fetching from a database with LIMIT/OFFSET
    $offset = ($page - 1) * $pageSize;
    $data = fetchFromDatabase($offset, $pageSize);

    // Return data directly as a generator
    foreach ($data as $item) {
        yield $item;
    }
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

// Get count of items that were actually fetched and yielded
$fetchedCount = $result->getFetchedCount(); // Returns count of items yielded by the result

// Advanced usage: Direct generator access
$generator = $adapter->generator(50, 50);
foreach ($generator as $item) {
    // Process items directly from generator
}
```

### Canonical adapter behavior

- `offset`, `limit`, and `nowCount` must be non-negative. Negative values throw `InvalidArgumentException`.
- `limit=0` is treated as a no-op **only** when `offset=0` and `nowCount=0`; otherwise it is rejected. This prevents
  accidental “fetch everything” or logic recursion loops.
- The adapter stops iterating once it has yielded `limit` items (for `limit>0`), when the wrapped logic reports a
  non-positive page size, or when the source returns no items. This guards against infinite loops from odd logic
  mappings.
- Offset smaller than limit is passed through to `somework/offset-page-logic` which may request smaller pages first; the
  adapter will keep iterating until the requested `limit` is satisfied or the source ends.
- Offset greater than limit and not divisible by it is mapped via the logic library’s divisor search (e.g. `offset=47`,
  `limit=22` → page `48`, size `1`); the adapter caps the total items at the requested `limit` but preserves the logic
  mapping.

Example mapping:

```php
// offset=3, limit=5
$result = $adapter->execute(3, 5);
// internally the logic library requests page 2 size 3, then page 4 size 2; adapter stops after 5 items total
$result->fetchAll(); // [4,5,6,7,8]
```

### Source Implementation

Your data source must return a `\Generator` that yields the items for the requested page. The generator will be consumed lazily by the pagination system.

### Using Custom Source Classes

You can also implement `SourceInterface` directly:

```php
use SomeWork\OffsetPage\SourceInterface;

/**
 * @template T
 * @implements SourceInterface<T>
 */
class MyApiSource implements SourceInterface
{
    /**
     * @return \Generator<T>
     */
    public function execute(int $page, int $pageSize): \Generator
    {
        // Fetch data from your API using pages
        $offset = ($page - 1) * $pageSize;
        $response = $this->apiClient->getItems($offset, $pageSize);

        // Yield data directly
        foreach ($response->data as $item) {
            yield $item;
        }
    }
}

$adapter = new OffsetAdapter(new MyApiSource());
$result = $adapter->execute(100, 25);
```

### Advanced Features

#### Exception Handling

The library provides a comprehensive exception hierarchy for better error handling:

```php
use SomeWork\OffsetPage\Exception\PaginationExceptionInterface;
use SomeWork\OffsetPage\Exception\InvalidPaginationArgumentException;

try {
    $result = $adapter->execute(-1, 50); // Invalid negative offset
} catch (InvalidPaginationArgumentException $e) {
    // Handle parameter validation errors
    echo $e->getMessage();
} catch (PaginationExceptionInterface $e) {
    // Handle any pagination-related error
}
```

#### Static Factories

Create empty results without complex setup:

```php
use SomeWork\OffsetPage\OffsetResult;

// Create an empty result
$emptyResult = OffsetResult::empty();
$items = $emptyResult->fetchAll(); // []
$count = $emptyResult->getFetchedCount(); // 0
```

#### Direct Generator Access

For advanced use cases, access generators directly:

```php
// From adapter
$generator = $adapter->generator(50, 25);

// From result
$result = $adapter->execute(50, 25);
$generator = $result->generator();
```

## How It Works

This library uses [somework/offset-page-logic](https://github.com/somework/offset-page-logic) internally to convert
offset-based requests to page-based requests. When you request items with an offset and limit, the library:

1. Calculates which pages need to be fetched
2. Calls your source for each required page
3. Combines the results into a single offset-based result

This is particularly useful when working with APIs or databases that only support page-based pagination but your
application logic requires offset-based access.

## Migration Guide (v2.x to v3.0)

Version 3.0 introduces several breaking changes for improved architecture:

### Breaking Changes

1. **Method Renamed**: `OffsetResult::getTotalCount()` → `OffsetResult::getFetchedCount()`
   ```php
   // Before
   $count = $result->getTotalCount();

   // After
   $count = $result->getFetchedCount();
   ```

2. **Interface Removed**: `SourceResultInterface` and `SourceResultCallbackAdapter` are removed
   ```php
   // Before
   public function execute(int $page, int $pageSize): SourceResultInterface

   // After
   public function execute(int $page, int $pageSize): \Generator
   ```

3. **Simplified Architecture**: Sources now return generators directly instead of wrapped interfaces

### New Features

- Comprehensive exception architecture with typed exception hierarchy
- Direct generator access methods on `OffsetAdapter` and `OffsetResult`
- `OffsetResult::empty()` static factory for empty results
- Enhanced type safety with `positive-int` types
- Improved parameter validation with detailed error messages

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
- Exception handling scenarios

## Author

[Igor Pinchuk](https://github.com/somework) - <i.pinchuk.work@gmail.com>

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
