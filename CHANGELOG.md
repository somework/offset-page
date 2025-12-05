# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/spec2.0.0.html).

## [2.0.0] - 2025-12-04

## [2.1.0] - 2026-01-04

### Changed

- Added strict input validation to `OffsetAdapter::execute`, rejecting negative values and non-zero `limit=0` usages.
- Introduced deterministic loop guards in the adapter to prevent infinite pagination when the logic library emits empty
  or zero-sized pages.
- Documented canonical behaviors in the README, including zero-limit sentinel handling and divisor-based offset mapping.

### Fixed

- Prevented endless iteration when sources return empty data or when the logic library signals no further items.

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
- Improved property visibility in `OffsetResult` (changed `protected` to `private`)
- Fixed logic error in `OffsetResult::fetchAll()` method

### Removed

- Removed legacy CI configurations (if any existed)
- Removed deprecated code patterns and old PHP syntax

### Fixed

- Fixed incorrect while loop condition in `OffsetResult::fetchAll()`

### Dev

- Added `ci` composer script for running all quality checks at once
- Improved CI workflow to use consolidated quality checks
- Enhanced Dependabot configuration with better commit message prefixes
- Added explicit PHP version specification to PHPStan configuration
- Improved property declarations using PHP 8.2+ features (readonly properties)
- Added library type specification and stability settings to composer.json

### Dev

- Migrated from Travis CI to GitHub Actions
- Added comprehensive CI pipeline with tests, static analysis, and code style checks
- Added composer scripts: `test`, `stan`, `cs-check`, `cs-fix`

## [1.x] - Previous Versions

For changes in version 1.x and earlier, please refer to the git history or previous documentation.
