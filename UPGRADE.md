# Upgrade Guide

This guide covers breaking changes and migration paths between major versions of the offset-page library.

## From v2.x to v3.0

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

### Migration Steps

1. Update method calls:
   ```php
   // Change this:
   $count = $result->getTotalCount();

   // To this:
   $count = $result->getFetchedCount();
   ```

2. Update source implementations:
   ```php
   // Change this:
   class MySource implements SourceInterface {
       public function execute(int $page, int $pageSize): SourceResultInterface {
           // ... implementation
           return new SourceResultCallbackAdapter($callback);
       }
   }

   // To this:
   class MySource implements SourceInterface {
       public function execute(int $page, int $pageSize): \Generator {
           // ... implementation
           yield $item; // or return a generator
       }
   }
   ```

3. Update imports:
   ```php
   // Remove these imports:
   use SomeWork\OffsetPage\SourceResultInterface;
   use SomeWork\OffsetPage\SourceResultCallbackAdapter;
   ```

## From v1.x to v2.0

Version 2.0 was a major rewrite with PHP 8.2 requirement and new architecture. See the [CHANGELOG.md](CHANGELOG.md) for details.

---

## Semantic Versioning

This library follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

## Support

- 📖 [Documentation](README.md)
- 🐛 [Issues](https://github.com/somework/offset-page/issues)
- 💬 [Discussions](https://github.com/somework/offset-page/discussions)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup and contribution guidelines.