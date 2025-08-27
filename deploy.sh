#!/bin/bash

# Deploy script - Sync src/ directory to BGA mount
# Run this after making changes to deploy them to BGA for testing

echo "🚀 Deploying files from src/ to BGA mount..."

# Check if src directory exists and has files
if [ ! -d src ] || [ -z "$(ls -A src 2>/dev/null)" ]; then
    echo "❌ Error: src/ directory not found or empty"
    echo "Please run ./pull.sh first to import BGA files"
    exit 1
fi

# Check if mount exists
if [ ! -d ~/BGA_mount ]; then
    echo "❌ Error: BGA mount not found at ~/BGA_mount"
    echo "Please run ./mount_bga.sh first"
    exit 1
fi

# Check for --watch flag
if [ "$1" == "--watch" ]; then
    echo "👁️  Watch mode enabled. Monitoring src/ for changes..."
    echo "Press Ctrl+C to stop"
    
    # Check if fswatch is installed
    if ! command -v fswatch &> /dev/null; then
        echo "⚠️  fswatch not installed. Install it with: brew install fswatch"
        echo "Falling back to single deploy..."
        WATCH_MODE=false
    else
        WATCH_MODE=true
    fi
else
    WATCH_MODE=false
fi

# Function to perform sync
perform_sync() {
    echo "📤 Syncing files to ~/BGA_mount..."
    rsync -av --delete \
        --exclude '.git' \
        --exclude '.svn' \
        --exclude '.DS_Store' \
        --exclude 'Thumbs.db' \
        --exclude '*.swp' \
        --exclude '*.swo' \
        --exclude '*~' \
        src/ ~/BGA_mount/
    
    if [ $? -eq 0 ]; then
        echo "✅ Deploy successful at $(date '+%H:%M:%S')"
        return 0
    else
        echo "❌ Deploy failed"
        return 1
    fi
}

# Perform initial sync
perform_sync

# If watch mode, start watching
if [ "$WATCH_MODE" == "true" ]; then
    echo ""
    echo "🔄 Watching for changes..."
    fswatch -o src/ | while read f; do
        echo ""
        echo "📝 Change detected..."
        perform_sync
    done
else
    if [ "$1" == "--watch" ]; then
        echo ""
        echo "ℹ️  To enable auto-deploy, install fswatch:"
        echo "    brew install fswatch"
    fi
fi