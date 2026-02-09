#!/bin/bash
set -e

# Project configuration
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESKTOP_DIR="$HOME/Desktop" 
# Adjust Desktop path if running in specific Windows/WSL environments if needed
# For Git Bash/Cygwin on Windows, typically /c/Users/USERNAME/Desktop

echo "🚀 Starting Build Process..."
cd "$PROJECT_DIR"

# 1. Build the package
echo "⚙️  Running make package..."
make package FINALPACKAGE=1 messages=yes

# 2. Find the .dylib file
# Theos usually puts the dylib in .theos/obj/debug or .theos/obj/debug/arm64
echo "🔍 Searching for generated .dylib..."
DYLIB_PATH=$(find .theos/obj -name "*.dylib" | head -n 1)

if [ -z "$DYLIB_PATH" ]; then
    echo "❌ Error: Could not find any .dylib file. Build may have failed."
    exit 1
fi

echo "✅ Found: $DYLIB_PATH"

# 3. Copy to Desktop
echo "📂 Copying to Desktop as Final_Bypass.dylib..."
cp "$DYLIB_PATH" "$DESKTOP_DIR/Final_Bypass.dylib"

if [ -f "$DESKTOP_DIR/Final_Bypass.dylib" ]; then
    echo "🎉 Success! File saved to: $DESKTOP_DIR/Final_Bypass.dylib"
else
    echo "❌ Error: Failed to copy file."
    exit 1
fi

# 4. Cleanup
echo "🧹 Cleaning up..."
make clean

echo "✨ Done."
