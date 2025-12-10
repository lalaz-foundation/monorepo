#!/bin/bash

# Lalaz - Prepare Clean Repository
# This script prepares the codebase for a fresh Git repository
#
# Usage: ./tools/prepare-clean-repo.sh [target_directory]

set -e

SOURCE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_DIR="${1:-$SOURCE_DIR/../lalaz-clean}"

echo "🚀 Preparing clean Lalaz repository"
echo "   Source: $SOURCE_DIR"
echo "   Target: $TARGET_DIR"
echo ""

# Create target directory
mkdir -p "$TARGET_DIR"

# Files and directories to copy
INCLUDE=(
    ".editorconfig"
    ".gitignore"
    ".php-cs-fixer.php"
    "CONTRIBUTING.md"
    "LICENSE"
    "Makefile"
    "README.md"
    "composer.json"
    "docs"
    "packages"
    "phpstan.neon"
    "phpunit.xml"
    "starters"
    "tools"
    ".github"
)

# Files and directories to exclude (will not be copied)
# - .git (fresh repo)
# - .php-cs-fixer.cache (generated)
# - .phpunit.cache (generated)
# - composer.lock (will be regenerated)
# - coverage/ (generated)
# - coverage.xml (generated)
# - vendor/ (will be installed)
# - sandbox/ (development only)
# - ECOMMERCE.md (roadmap doc)
# - SAAS_KIT.md (roadmap doc)
# - lalaz.code-workspace (IDE specific)
# - monorepo-builder.php (may not be needed)

echo "📁 Copying files..."

for item in "${INCLUDE[@]}"; do
    if [ -e "$SOURCE_DIR/$item" ]; then
        # Use rsync to handle symlinks properly and exclude vendor
        rsync -a --exclude='vendor' --exclude='.phpunit.cache' --exclude='composer.lock' \
              --exclude='*.cache' --exclude='coverage*' \
              "$SOURCE_DIR/$item" "$TARGET_DIR/"
        echo "   ✓ $item"
    else
        echo "   ⚠ $item (not found, skipping)"
    fi
done

# Clean up vendor directories in packages
echo ""
echo "🧹 Cleaning up vendor directories..."
find "$TARGET_DIR/packages" -name "vendor" -type d -exec rm -rf {} + 2>/dev/null || true
find "$TARGET_DIR/packages" -name ".phpunit.cache" -type d -exec rm -rf {} + 2>/dev/null || true
find "$TARGET_DIR/packages" -name "composer.lock" -type f -delete 2>/dev/null || true

# Clean up starters
echo "🧹 Cleaning up starters..."
find "$TARGET_DIR/starters" -name "vendor" -type d -exec rm -rf {} + 2>/dev/null || true
find "$TARGET_DIR/starters" -name ".phpunit.cache" -type d -exec rm -rf {} + 2>/dev/null || true
find "$TARGET_DIR/starters" -name "composer.lock" -type f -delete 2>/dev/null || true
rm -rf "$TARGET_DIR/starters/*/storage/cache/"* 2>/dev/null || true
rm -rf "$TARGET_DIR/starters/*/storage/logs/"* 2>/dev/null || true

# Create .gitkeep files for empty directories
echo "📄 Creating .gitkeep files..."
for dir in "$TARGET_DIR/starters/*/storage/cache" "$TARGET_DIR/starters/*/storage/logs"; do
    if [ -d "$(dirname "$dir")" ]; then
        mkdir -p "$dir"
        touch "$dir/.gitkeep"
    fi
done

# Summary
echo ""
echo "✅ Clean repository prepared at: $TARGET_DIR"
echo ""
echo "📋 Next steps:"
echo "   1. cd $TARGET_DIR"
echo "   2. git init"
echo "   3. git add -A"
echo "   4. git commit -m 'Initial commit: Lalaz v1.0.0-rc.1'"
echo "   5. git remote add origin git@github.com:lalaz-foundation/lalaz.git"
echo "   6. git branch -M main"
echo "   7. git push -u origin main"
echo "   8. git checkout -b develop"
echo "   9. git push -u origin develop"
echo ""
echo "🏷️ To create first release:"
echo "   git tag v1.0.0-rc.1"
echo "   git push origin v1.0.0-rc.1"
