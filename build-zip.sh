#!/bin/bash
#
# Build WooCommerce plugin ZIP for distribution
#
# Usage: ./build-zip.sh [version]
#
# This creates a boglepay-gateway-{version}.zip file ready for WordPress installation

set -e

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/boglepay-gateway"
OUTPUT_DIR="$SCRIPT_DIR/dist"

# Get version from argument or plugin file
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep "Version:" "$PLUGIN_DIR/boglepay-gateway.php" | head -1 | awk '{print $3}')
fi

echo "Building Bogle Pay Gateway v$VERSION..."

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Create a clean temp directory
TEMP_DIR=$(mktemp -d)
TEMP_PLUGIN_DIR="$TEMP_DIR/boglepay-gateway"

# Copy plugin files
mkdir -p "$TEMP_PLUGIN_DIR"
cp -r "$PLUGIN_DIR/"* "$TEMP_PLUGIN_DIR/"

# Remove development files if any
rm -rf "$TEMP_PLUGIN_DIR/.git" 2>/dev/null || true
rm -rf "$TEMP_PLUGIN_DIR/node_modules" 2>/dev/null || true
rm -f "$TEMP_PLUGIN_DIR/.gitignore" 2>/dev/null || true
rm -f "$TEMP_PLUGIN_DIR/.editorconfig" 2>/dev/null || true
rm -f "$TEMP_PLUGIN_DIR/composer.json" 2>/dev/null || true
rm -f "$TEMP_PLUGIN_DIR/composer.lock" 2>/dev/null || true
rm -f "$TEMP_PLUGIN_DIR/package.json" 2>/dev/null || true
rm -f "$TEMP_PLUGIN_DIR/package-lock.json" 2>/dev/null || true

# Create ZIP file
OUTPUT_FILE="$OUTPUT_DIR/boglepay-gateway-$VERSION.zip"
rm -f "$OUTPUT_FILE" 2>/dev/null || true

cd "$TEMP_DIR"
zip -r "$OUTPUT_FILE" boglepay-gateway -x "*.DS_Store" -x "*__MACOSX*"

# Cleanup
rm -rf "$TEMP_DIR"

echo ""
echo "✅ Plugin ZIP created successfully!"
echo "   $OUTPUT_FILE"
echo ""
echo "To install:"
echo "1. Go to WordPress Admin → Plugins → Add New → Upload Plugin"
echo "2. Upload the ZIP file"
echo "3. Click 'Install Now' then 'Activate'"
