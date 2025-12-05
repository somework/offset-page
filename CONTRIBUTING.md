# Contributing

Thank you for your interest in contributing to the offset-page library! This document provides guidelines and information for contributors.

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/somework/offset-page.git
   cd offset-page
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests to ensure everything works:
   ```bash
   composer test
   ```

## Development Workflow

### Available Scripts

This project includes several composer scripts for development:

```bash
composer test          # Run PHPUnit tests
composer test-coverage # Run tests with coverage reports
composer stan          # Run PHPStan static analysis
composer cs-check      # Check code style with PHP-CS-Fixer
composer cs-fix        # Fix code style issues with PHP-CS-Fixer
composer quality       # Run static analysis and code style checks
```

### Code Style

This project uses:
- **PHP-CS-Fixer** for code style enforcement
- **PHPStan** (level 9) for static analysis
- **PSR-12** coding standard

Before submitting a pull request, ensure:
```bash
composer quality  # Should pass without errors
composer test     # All tests should pass
```

### Testing

- Write tests for new features and bug fixes
- Maintain or improve code coverage
- Run the full test suite before submitting changes

## Pull Request Process

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/your-feature-name`
3. **Make** your changes following the code style guidelines
4. **Test** your changes: `composer test && composer quality`
5. **Commit** your changes with descriptive commit messages
6. **Push** to your fork
7. **Create** a Pull Request with a clear description

### Pull Request Guidelines

- Use a clear, descriptive title
- Provide a detailed description of the changes
- Reference any related issues
- Ensure all CI checks pass
- Keep changes focused and atomic

## Reporting Issues

When reporting bugs or requesting features:

- Use the GitHub issue tracker
- Provide a clear description of the issue
- Include code examples or reproduction steps
- Specify your PHP version and environment

## Code of Conduct

This project follows a code of conduct to ensure a welcoming environment for all contributors. By participating, you agree to:

- Be respectful and inclusive
- Focus on constructive feedback
- Accept responsibility for mistakes
- Show empathy towards other contributors

## License

By contributing to this project, you agree that your contributions will be licensed under the same license as the project (MIT License).

## Questions?

If you have questions about contributing, feel free to:
- Open a discussion on GitHub
- Check existing issues and pull requests
- Review the documentation in [README.md](README.md)

Thank you for contributing to offset-page! 🎉