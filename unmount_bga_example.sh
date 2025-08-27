#!/bin/bash
echo "Unmounting BGA folder..."
umount ~/BGA_mount
if [ $? -eq 0 ]; then
    echo "✅ Unmounted successfully"
else
    echo "❌ Unmount failed"
fi