#!/usr/bin/env bash
# cPanel / shared hosting — সাইটের tools/ ফোল্ডারে yt-dlp বাইনারি
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/tools/yt-dlp"
URL="https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp"
echo "Installing yt-dlp to $DEST"
if command -v curl >/dev/null 2>&1; then
  curl -fsSL "$URL" -o "$DEST"
elif command -v wget >/dev/null 2>&1; then
  wget -qO "$DEST" "$URL"
else
  echo "curl বা wget লাগবে" >&2
  exit 1
fi
chmod +x "$DEST"
"$DEST" --version
echo "OK — অ্যাডমিন পেজ রিফ্রেশ করে YouTube ইম্পোর্ট চেষ্টা করুন।"