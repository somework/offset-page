# Offset Page

[![CI](https://img.shields.io/github/actions/workflow/status/somework/offset-page/ci.yml?branch=master&label=CI)](https://github.com/somework/offset-page/actions/workflows/ci.yml?query=branch%3Amaster)
[![Latest Stable Version](https://img.shields.io/packagist/v/somework/offset-page.svg)](https://packagist.org/packages/somework/offset-page)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/somework/offset-page.svg)](https://packagist.org/packages/somework/offset-page)

**Transform page-based APIs into offset-based pagination with zero hassle**

Convert any page-based data source (APIs, databases, external services) into seamless offset-based pagination. Perfect for when your app needs "give me items 50-99" but your data source only speaks "give me page 3 with 25 items each".

✨ **Framework-agnostic** • 🚀 **High performance** • 🛡️ **Type-safe** • 🧪 **Well tested**

## Why This Package?

**The Problem**: Your application uses offset-based pagination ("show items 100-199"), but your database or API only supports page-based pagination ("give me page 5 with 20 items").

**Manual Solution**: Write complex math to convert offsets to pages, handle edge cases, manage memory efficiently, and deal with different data source behaviors.

**This Package**: Handles all the complexity automatically. Just provide a callback that fetches pages, and get seamless offset-based access.

### Why Choose This Over Manual Implementation?

- ✅ **Zero Boilerplate** - One callback function vs dozens of lines of pagination math
- ✅ **Memory Efficient** - Lazy loading prevents loading unnecessary data
- ✅ **Type Safe** - Full PHP 8.2+ type safety with generics
- ✅ **Well Tested** - Comprehensive test suite covering edge cases
- ✅ **Framework Agnostic** - Works with any PHP project (Laravel, Symfony, plain PHP, etc.)
- ✅ **Production Ready** - Used in real applications with battle-tested logic

### Why Choose This Over Framework-Specific Solutions?

Unlike Laravel's `paginate()` or Symfony's pagination components that are tied to specific frameworks and ORMs, this package:

- Works with **any data source** (SQL, NoSQL, REST APIs, GraphQL, external services)
- Has **zero dependencies** on frameworks or ORMs
- Provides **consistent behavior** across different projects and teams
- Is **future-proof** - not tied to any framework's roadmap

## Installation

```bash
composer require somework/offset-page
```

## Quickstart

**Get started in 30 seconds:**

```php
use SomeWork\OffsetPage\OffsetAdapter;

// Your page-based API or database function
function fetchPage(int $page, int $pageSize): array {
    $offset = ($page - 1) * $pageSize;
    // Your database query or API call here
    return fetchFromDatabase($offset, $pageSize);
}

// Create adapter with a callback
$adapter = OffsetAdapter::fromCallback(function (int $page, int $pageSize) {
    $data = fetchPage($page, $pageSize);
    foreach ($data as $item) {
        yield $item;
    }
});

// Get items 50-99 (that's offset 50, limit 50)
$items = $adapter->fetchAll(50, 50);

// That's it! Your page-based source now works with offset-based requests.
```

## How It Works

The adapter automatically converts your offset-based requests into page-based requests:

```php
// You want: "Give me items 50-99"
$items = $adapter->fetchAll(50, 50);

// The adapter translates this into:
// Page 3 (items 51-75), Page 4 (items 76-100)
// Then returns exactly items 50-99 from the results
```

## Usage Patterns

### Database with LIMIT/OFFSET

```php
$adapter = OffsetAdapter::fromCallback(function (int $page, int $pageSize) {
    $offset = ($page - 1) * $pageSize;

    $stmt = $pdo->prepare("SELECT * FROM users LIMIT ? OFFSET ?");
    $stmt->execute([$pageSize, $offset]);

    foreach ($stmt->fetchAll() as $user) {
        yield $user;
    }
});

$users = $adapter->fetchAll(100, 25); // Users 100-124
```

### REST API with Page Parameters

```php
$adapter = OffsetAdapter::fromCallback(function (int $page, int $pageSize) {
    $response = $httpClient->get("/api/products?page={$page}&size={$pageSize}");
    $data = json_decode($response->getBody(), true);

    foreach ($data['products'] as $product) {
        yield $product;
    }
});

$products = $adapter->fetchAll(50, 20); // Products 50-69
```

### Custom Source Implementation

For complex scenarios, implement `SourceInterface`:

```php
use SomeWork\OffsetPage\SourceInterface;

class DatabaseSource implements SourceInterface
{
    public function __construct(private PDO $pdo) {}

    public function execute(int $page, int $pageSize): \Generator
    {
        $offset = ($page - 1) * $pageSize;
        $stmt = $this->pdo->prepare("SELECT * FROM items LIMIT ? OFFSET ?");
        $stmt->execute([$pageSize, $offset]);

        foreach ($stmt->fetchAll() as $item) {
            yield $item;
        }
    }
}

$adapter = new OffsetAdapter(new DatabaseSource($pdo));
$items = $adapter->fetchAll(1000, 100);
```

## Advanced Usage

### Error Handling

The library provides specific exceptions for different error types:

```php
use SomeWork\OffsetPage\Exception\InvalidPaginationArgumentException;
use SomeWork\OffsetPage\Exception\PaginationExceptionInterface;

try {
    $result = $adapter->fetchAll(-1, 50); // Invalid!
} catch (InvalidPaginationArgumentException $e) {
    echo "Invalid parameters: " . $e->getMessage();
} catch (PaginationExceptionInterface $e) {
    echo "Pagination error: " . $e->getMessage();
}
```

### Streaming Results

For memory-efficient processing of large result sets:

```php
$result = $adapter->execute(1000, 500);

while ($item = $result->fetch()) {
    processItem($item); // Process one at a time
}
```

### Getting Result Metadata

```php
$result = $adapter->execute(50, 25);
$items = $result->fetchAll();
$count = $result->getFetchedCount(); // Number of items actually returned
```

## Upgrading

See [UPGRADE.md](UPGRADE.md) for migration guides between major versions, including breaking changes and upgrade paths.

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