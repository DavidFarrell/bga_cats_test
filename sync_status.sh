#!/bin/bash

# Sync status script - Check differences between src/ and BGA mount

echo "📊 Checking sync status between src/ and BGA mount..."
echo ""

# Check if src directory exists
if [ ! -d src ]; then
    echo "⚠️  src/ directory not found"
    echo "   Run ./pull.sh to create it"
    SRC_EXISTS=false
else
    SRC_EXISTS=true
    SRC_COUNT=$(find src -type f 2>/dev/null | wc -l | tr -d ' ')
    echo "📁 src/ directory: $SRC_COUNT files"
fi

# Check if mount exists and has files
if [ ! -d ~/BGA_mount ] || [ -z "$(ls -A ~/BGA_mount 2>/dev/null)" ]; then
    echo "⚠️  BGA mount not found or empty at ~/BGA_mount"
    echo "   Run ./mount_bga.sh to mount it"
    MOUNT_EXISTS=false
else
    MOUNT_EXISTS=true
    MOUNT_COUNT=$(find ~/BGA_mount -type f 2>/dev/null | wc -l | tr -d ' ')
    echo "📁 BGA mount: $MOUNT_COUNT files"
fi

echo ""

# If both exist, show differences
if [ "$SRC_EXISTS" == "true" ] && [ "$MOUNT_EXISTS" == "true" ]; then
    echo "🔍 Checking for differences..."
    echo ""
    
    # Use rsync in dry-run mode to show what would be synced
    DIFF_OUTPUT=$(rsync -av --dry-run --delete \
        --exclude '.git' \
        --exclude '.svn' \
        --exclude '.DS_Store' \
        --exclude 'Thumbs.db' \
        src/ ~/BGA_mount/ 2>&1)
    
    # Check if there are differences
    if echo "$DIFF_OUTPUT" | grep -q "^deleting\|^[^/]"; then
        echo "⚠️  Differences found:"
        echo ""
        echo "$DIFF_OUTPUT" | grep "^deleting\|^[^/]" | head -20
        
        # Count differences
        DIFF_COUNT=$(echo "$DIFF_OUTPUT" | grep -c "^deleting\|^[^/]")
        if [ $DIFF_COUNT -gt 20 ]; then
            echo "... and $((DIFF_COUNT - 20)) more differences"
        fi
        
        echo ""
        echo "💡 Run ./deploy.sh to sync these changes to BGA"
    else
        echo "✅ src/ and BGA mount are in sync!"
    fi
elif [ "$SRC_EXISTS" == "false" ] && [ "$MOUNT_EXISTS" == "true" ]; then
    echo "💡 Run ./pull.sh to import BGA files to src/"
elif [ "$SRC_EXISTS" == "true" ] && [ "$MOUNT_EXISTS" == "false" ]; then
    echo "💡 Run ./mount_bga.sh to mount BGA, then ./deploy.sh to upload"
else
    echo "❌ Neither src/ nor BGA mount are available"
    echo ""
    echo "📝 Next steps:"
    echo "  1. Run ./mount_bga.sh to mount BGA"
    echo "  2. Run ./pull.sh to import files to src/"
fi