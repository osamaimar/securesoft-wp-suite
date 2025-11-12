#!/bin/bash

# Build script for SS Core Licenses plugin
# Creates a distributable ZIP file

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="ss-core-licenses"
VERSION=$(grep -oP "Version:\s*\K[0-9.]+" "$PLUGIN_DIR/ss-core-licenses.php")
BUILD_DIR="$PLUGIN_DIR/dist"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"

echo "Building ${PLUGIN_NAME} version ${VERSION}..."

# Create build directory
mkdir -p "$BUILD_DIR"

# Create temporary directory for plugin files
TEMP_DIR=$(mktemp -d)
PLUGIN_BUILD_DIR="$TEMP_DIR/$PLUGIN_NAME"

# Copy plugin files
echo "Copying plugin files..."
rsync -av --progress \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='.gitignore' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpcs.xml' \
  --exclude='README.md' \
  --exclude='build.sh' \
  --exclude='dist' \
  --exclude='.DS_Store' \
  "$PLUGIN_DIR/" "$PLUGIN_BUILD_DIR/"

# Create ZIP file
echo "Creating ZIP file..."
cd "$TEMP_DIR"
zip -r "$BUILD_DIR/$ZIP_NAME" "$PLUGIN_NAME" -q

# Cleanup
rm -rf "$TEMP_DIR"

echo "✓ Build complete: $BUILD_DIR/$ZIP_NAME"
echo "  Size: $(du -h "$BUILD_DIR/$ZIP_NAME" | cut -f1)"

