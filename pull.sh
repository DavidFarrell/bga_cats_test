#!/bin/bash

# Pull script - Import BGA files from mount to src/ directory
# This is typically run once to get the initial files from BGA

echo "🔄 Pulling files from BGA mount to src/ directory..."

# Check if mount exists and has files
if [ ! -d ~/BGA_mount ] || [ -z "$(ls -A ~/BGA_mount 2>/dev/null)" ]; then
    echo "❌ Error: BGA mount not found or empty at ~/BGA_mount"
    echo "Please run ./mount_bga.sh first"
    exit 1
fi

# Create src directory if it doesn't exist
if [ ! -d src ]; then
    echo "📁 Creating src/ directory..."
    mkdir -p src
fi

# Use rsync to copy files from mount to src
echo "📥 Copying files from ~/BGA_mount to src/..."
rsync -av --delete \
    --exclude '.git' \
    --exclude '.svn' \
    --exclude '.DS_Store' \
    --exclude 'Thumbs.db' \
    ~/BGA_mount/ src/

if [ $? -eq 0 ]; then
    echo "✅ Successfully pulled files from BGA to src/"
    echo ""
    echo "📊 Summary:"
    echo "  Files in src/: $(find src -type f | wc -l | tr -d ' ')"
    echo "  Directories: $(find src -type d | wc -l | tr -d ' ')"
    echo ""
    echo "You can now work on files in the src/ directory!"
else
    echo "❌ Error occurred while pulling files"
    exit 1
fi