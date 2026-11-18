#!/usr/bin/env bash
# One-command Hostinger staging: no file copy.
# Points public_html/staging at the already-uploaded staging_public_html.
set -euo pipefail
HOME_DIR="${HOME:-/home/YOUR_HOST_USER}"
SRC="$HOME_DIR/staging_public_html"
DEST="$HOME_DIR/public_html/staging"

if [ ! -d "$SRC" ]; then
  echo "Missing $SRC"
  exit 1
fi
if [ -e "$DEST" ] && [ ! -L "$DEST" ]; then
  echo "$DEST already exists and is not a link. Move it aside first."
  exit 1
fi
ln -sfn "$SRC" "$DEST"
echo "Linked $DEST -> $SRC"
ls -ld "$DEST"
