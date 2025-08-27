#!/bin/bash
echo "Mounting BGA herdingcats folder..."
echo "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" | sshfs -o password_stdin PaidiaGames@1.studio.boardgamearena.com:/herdingcats ~/BGA_mount -p 2022
if [ $? -eq 0 ]; then
    echo "✅ Mount successful! Files available at ~/BGA_mount"
    ls -la ~/BGA_mount
else
    echo "❌ Mount failed. Check that macFUSE is running."
fi