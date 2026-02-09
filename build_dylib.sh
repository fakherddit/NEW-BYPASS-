#!/bin/bash

# Script to automate building and copying the dylib to Desktop
# Usage: ./build_dylib.sh

# Name of the final file
FINAL_NAME="Safety_Bypass.dylib"

# Desktop Path (Assuming the project folder is ON the desktop, so parent dir is Desktop)
DESTINATION="../"

echo "=========================================="
echo "      iOS Tweak Build Automation"
echo "=========================================="

# Check and set THEOS variable
if [ -z "$THEOS" ]; then
    if [ -d "$HOME/theos" ]; then
        export THEOS="$HOME/theos"
        echo "[*] THEOS found at home dir: $THEOS"
    elif [ -d "/opt/theos" ]; then
        export THEOS="/opt/theos"
        echo "[*] THEOS found at /opt/theos"
    elif [ -d "/var/theos" ]; then
        export THEOS="/var/theos"
        echo "[*] THEOS found at /var/theos"
    else
        echo "[!] Error: THEOS environment variable not set and not found in common locations."
        echo "    Please set THEOS variable or install Theos."
        exit 1
    fi
else
    echo "[*] Using explicit THEOS: $THEOS"
fi

# Fix permissions for .deb creation
echo "[*] Fixing permissions..."
chmod -R 0755 control

# 1. Clean previous builds
echo "[*] Cleaning previous builds (make clean)..."
make clean

# 2. Compile the project
echo "[*] Building project (make package)..."
# Using messages=yes to see build details
make package messages=yes

# We don't exit immediately on error because make package might fail at 'dpkg-deb' stage 
# (common on WSL due to permissions) but the dylib might still be built.

# 3. Find the generated .dylib file
# Usually located in .theos/obj/debug/ or .theos/obj/
echo "[*] Searching for built .dylib file..."

# Find the first .dylib file in .theos/obj
DYLIB_PATH=$(find .theos/obj -type f -name "*.dylib" | head -n 1)

if [ -z "$DYLIB_PATH" ]; then
    echo "[!] Error: Could not find any .dylib file in .theos/obj"
    exit 1
fi

echo "[*] Found dylib: $DYLIB_PATH"

# 4. Copy to Desktop with new name
echo "[*] Copying to Desktop as '$FINAL_NAME'..."

cp "$DYLIB_PATH" "${DESTINATION}${FINAL_NAME}"

if [ $? -eq 0 ]; then
    echo "[+] Success! Dylib saved to: ${DESTINATION}${FINAL_NAME}"
else
    echo "[!] Error: Failed to copy dylib to Desktop."
fi

# 5. Copy .deb file if it exists
DEB_PATH=$(find packages -type f -name "*.deb" | head -n 1)
if [ ! -z "$DEB_PATH" ]; then
    echo "[*] Found .deb package: $DEB_PATH"
    cp "$DEB_PATH" "${DESTINATION}"
    echo "[+] Success! .deb saved to Desktop"
else
    echo "[!] Warning: No .deb package found in ./packages/"
fi

# 6. Clean up
echo "[*] Cleaning up workspace & logs..."
make clean
rm -f build_log.txt build_output.txt
echo "[*] Log cleanup complete."

echo "=========================================="
echo "           Process Completed"
echo "=========================================="
