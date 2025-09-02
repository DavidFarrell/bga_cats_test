#!/bin/bash

# Deploy script - Sync src/ directory to BGA mount
# Run this after making changes to deploy them to BGA for testing

echo "🚀 Deploying files from src/ to BGA mount..."

# First unmount if already mounted
echo "🔄 Unmounting BGA if already mounted..."
./unmount_bga.sh 2>/dev/null

# Mount BGA
echo "📁 Mounting BGA..."
./mount_bga.sh
if [ $? -ne 0 ]; then
    echo "❌ Error: Failed to mount BGA"
    exit 1
fi

# Check if src directory exists and has files
if [ ! -d src ] || [ -z "$(ls -A src 2>/dev/null)" ]; then
    echo "❌ Error: src/ directory not found or empty"
    echo "Please run ./pull.sh first to import BGA files"
    exit 1
fi

# Check if mount exists (should be there after mounting)
if [ ! -d ~/BGA_mount ]; then
    echo "❌ Error: BGA mount not found at ~/BGA_mount after mounting"
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

# If watch mode, start watching with multiple methods
if [ "$WATCH_MODE" == "true" ]; then
    echo ""
    echo "🔄 Watching for changes with multiple methods..."
    
    # Use both fswatch and inotifywait for better coverage
    if command -v inotifywait &> /dev/null; then
        echo "📡 Using inotifywait as backup watcher..."
        # Linux-style watching as backup
        (inotifywait -m -r -e modify,create,delete src/ 2>/dev/null | while read path action file; do
            echo ""
            echo "📝 Change detected via inotify: $action $file"
            sleep 0.3
            perform_sync
        done) &
        INOTIFY_PID=$!
    fi
    
    # Main fswatch with debouncing
    echo "👁️  Using fswatch as primary watcher..."
    fswatch -o -r src/ | while read f; do
        echo ""
        echo "📝 Change detected via fswatch..."
        
        # Debounce rapid changes
        sleep 0.5
        
        perform_sync
    done
    
    # Clean up background processes if we exit
    if [ ! -z "$INOTIFY_PID" ]; then
        kill $INOTIFY_PID 2>/dev/null
    fi
else
    if [ "$1" == "--watch" ]; then
        echo ""
        echo "ℹ️  To enable auto-deploy, install fswatch:"
        echo "    brew install fswatch"
        echo "    For even better coverage, also install inotifywait:"
        echo "    brew install inotify-tools"
    fi
fi