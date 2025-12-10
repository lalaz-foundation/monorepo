# Contributing to Lalaz

Thank you for considering contributing to Lalaz! This document provides guidelines and information about contributing.

## Code of Conduct

Please be respectful and constructive in all interactions.

## 🌳 Branch Structure

```
main        → Stable releases (v1.0.0, v1.1.0, etc.)
develop     → Active development (next release)
```

### Branch Rules

| Branch | Purpose | Protection |
|--------|---------|------------|
| `main` | Production-ready code | Protected, requires PR |
| `develop` | Integration branch | Protected, requires PR |

## Getting Started

### Prerequisites

- PHP 8.3 or higher
- Composer 2.x
- Node.js 18+ (for documentation)
- Git

### Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/lalaz.git
   cd lalaz
   ```

3. Install dependencies:
   ```bash
   composer install
   
   # Install package dependencies
   for dir in packages/*/; do
       cd "$dir" && composer install && cd ../..
   done
   ```

## Development Workflow

### Running Tests

```bash
# All tests
./lalaz test

# Specific package
./lalaz test:package auth

# With coverage
./lalaz test:coverage
./lalaz test:coverage auth  # Single package
```

### Code Quality

```bash
# Check code style
./lalaz lint

# Fix code style
./lalaz lint:fix

# Static analysis
./lalaz analyze

# Run all checks
./lalaz check
```

### Documentation

```bash
# Start dev server
./lalaz docs

# Build
./lalaz docs:build
```

## Monorepo Structure

```
framework/
├── packages/           # Individual packages
│   ├── auth/
│   ├── cache/
│   ├── database/
│   └── ...
├── docs/              # VitePress documentation
├── starters/          # Project templates
└── sandbox/           # Development playground
```

Each package is independently versioned and can be installed separately:

```bash
composer require lalaz/auth
composer require lalaz/orm
```

## Making Changes

### Branch Naming

- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation changes
- `refactor/description` - Code refactoring

### Pull Requests

1. Create a branch from `develop`
2. Make your changes
3. Write/update tests
4. Ensure all checks pass
5. Update documentation if needed
6. Submit PR targeting `develop`

For hotfixes (urgent production bugs):
1. Create branch from `main`
2. Fix the issue
3. Submit PR targeting `main`
4. After merge, cherry-pick to `develop`

### PR Checklist

- [ ] Tests pass (`make test`)
- [ ] Code style is correct (`make lint`)
- [ ] Static analysis passes (`make analyze`)
- [ ] Documentation updated (if applicable)
- [ ] Changelog entry added (for features/fixes)

## Package Guidelines

### Creating Tests

```php
<?php

declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MyClass::class)]
final class MyClassTest extends TestCase
{
    #[Test]
    public function it_does_something(): void
    {
        // Arrange
        $subject = new MyClass();
        
        // Act
        $result = $subject->doSomething();
        
        // Assert
        $this->assertTrue($result);
    }
}
```

### Code Style

- PSR-12 coding standard
- Strict types declaration required
- Type declarations for all parameters and return types
- PHPDoc only when adding value beyond type hints

### Namespace Conventions

```
Lalaz\{Package}\             - Main namespace
Lalaz\{Package}\Contracts\   - Interfaces
Lalaz\{Package}\Concerns\    - Traits
Lalaz\{Package}\Exceptions\  - Exceptions
Lalaz\{Package}\Tests\       - Tests
```

## 🚀 Release Process

Releases are automated via GitHub Actions when a tag is created.

### Creating a Release

```bash
# Ensure develop is ready
git checkout develop
git pull origin develop

# Merge to main
git checkout main
git merge develop

# Create tag
git tag v1.0.0  # or v1.0.0-rc.1 for pre-releases
git push origin main --tags
```

### Version Format

We follow [Semantic Versioning](https://semver.org/):

```
MAJOR.MINOR.PATCH[-PRERELEASE]

Examples:
- 1.0.0        → First stable release
- 1.0.1        → Bug fix
- 1.1.0        → New feature (backward compatible)
- 2.0.0        → Breaking changes
- 1.0.0-rc.1   → Release candidate
- 1.0.0-beta.1 → Beta release
```

### What Happens on Release

1. **CI runs all tests** across PHP 8.3 and 8.4
2. **GitHub Release** is created with changelog
3. **Starters** are synced to their template repositories
4. **Packagist** is notified of the new version

## Getting Help

- [Documentation](https://lalaz.dev/docs)
- [GitHub Issues](https://github.com/lalaz-foundation/lalaz/issues)
- [Discussions](https://github.com/lalaz-foundation/lalaz/discussions)

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
