#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
out="$root/deliverables"
mkdir -p "$out"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

build_one() {
  local app="$1"
  local folder="$2"
  local dest="$stage/$folder"
  mkdir -p "$dest/logs" "$dest/uploads"
  rsync -a \
    --exclude '.env' \
    --exclude 'logs/*' \
    "$root/verticals/$app/" "$dest/"
  rsync -a "$root/verticals/core/" "$dest/core/"
  cp -f "$root/verticals/hotel/logs/.htaccess" "$dest/logs/.htaccess"
  cp -f "$root/verticals/hotel/uploads/.htaccess" "$dest/uploads/.htaccess"
  rm -f "$out/${folder}.zip"
  (cd "$stage" && zip -qr "$out/${folder}.zip" "$folder")
}

build_one hotel Hotel_Management_System
build_one grocery Grocery_Mudi_Management_System
build_one pharmacy Pharmacy_Management_System

ls -lh \
  "$out/Hotel_Management_System.zip" \
  "$out/Grocery_Mudi_Management_System.zip" \
  "$out/Pharmacy_Management_System.zip"
