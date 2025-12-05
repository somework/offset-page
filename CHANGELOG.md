# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/spec2.0.0.html).

## [3.0.0] - 2025-12-05

### Added

- Added comprehensive exception architecture with typed exception hierarchy:
  - `PaginationExceptionInterface` for type-safe exception catching
  - `PaginationException` as base exception class
  - `InvalidPaginationArgumentException` for parameter validation errors
  - `InvalidPaginationResultException` for result validation errors
- Added `OffsetAdapter::generator()` method for direct generator access
- Added `OffsetResult::empty()` static factory for creating empty results
- Added `OffsetResult::generator()` method for accessing internal generator
- Added strict input validation to `OffsetAdapter::execute()`, rejecting negative values and invalid `limit=0` combinations
- Added deterministic loop guards to prevent infinite pagination
- Enhanced `SourceInterface` documentation with comprehensive behavioral specifications

### Changed

- **BREAKING**: Renamed `OffsetResult::getTotalCount()` to `OffsetResult::getFetchedCount()` for semantic clarity
- **BREAKING**: Removed `SourceResultInterface` and `SourceResultCallbackAdapter` to simplify architecture
- **BREAKING**: `OffsetResult` no longer implements `SourceResultInterface`
- **BREAKING**: `SourceInterface::execute()` now returns `\Generator<T>` directly instead of wrapped interface
- Enhanced type safety with `positive-int` types in `SourceInterface` PHPDoc
- Reorganized test methods for better logical flow and maintainability
- Improved property visibility (changed some `protected` to `private` in `OffsetResult`)
- Updated `SourceCallbackAdapter` with enhanced validation and error messages

### Removed

- Removed `SourceResultInterface` and `SourceResultCallbackAdapter` classes
- Removed `ArraySourceResult` test utility class
- Removed `SourceResultCallbackAdapterTest` test class
- Removed unnecessary interface abstractions for cleaner architecture

### Fixed

- Fixed potential infinite loop scenarios in pagination logic
- Enhanced error messages for better developer experience
- Improved validation of pagination parameters

### Dev

- Enhanced test organization with logical method grouping
- Added comprehensive test coverage for new exception architecture
- Improved static analysis type safety
- Added 31 new tests for enhanced functionality

## [2.0.0] - 2025-12-04

### Added

- Added `declare(strict_types=1)` to all source files for improved type safety
- Added comprehensive README with usage examples and installation instructions
- Added PHP version badge to README
- Added GitHub Actions CI workflow with PHP 8.2 and 8.3 testing
- Added Dependabot configuration for automated dependency updates
- Added PHPStan static analysis configuration (level 9)
- Added PHP-CS-Fixer code style configuration

### Changed

- **BREAKING**: Updated minimum PHP version requirement from 7.4 to 8.2
- **BREAKING**: Updated `somework/offset-page-logic` dependency to `^2.0` (major version update)
- Updated PHPUnit to `^10.5` for PHP 8.2+ compatibility
- Updated PHPStan to `^2.1`
- Updated PHP-CS-Fixer to `^3.91`

### Dev

- Migrated from Travis CI to GitHub Actions
- Added comprehensive CI pipeline with tests, static analysis, and code style checks
- Added composer scripts: `test`, `stan`, `cs-check`, `cs-fix`

## [1.x] - Previous Versions

For changes in version 1.x and earlier, please refer to the git history or previous documentation.
