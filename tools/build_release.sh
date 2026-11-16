#!/usr/bin/env bash
# Build sanitized Hostinger ZIPs from the same file set.
# One-click archive uses a public_html/ prefix for domain-root extract
# and Easy Update. The folder archive is for extract-inside-staging-dir.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(tr -d '[:space:]' < "$root/VERSION.txt")"
out_dir="$root/deliverables"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

mkdir -p "$out_dir"
rsync -a \
  --exclude '.git/' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude '!.env.example' \
  --exclude 'deliverables/' \
  --exclude 'private_data/' \
  --exclude 'agent-tools/' \
  --exclude 'SPEED.txt' \
  --exclude 'downloads/*.zip' \
  --exclude 'releases/*.zip' \
  --exclude 'data/*.json' \
  --exclude 'data/*.sql' \
  --include 'uploads/' \
  --include 'uploads/.htaccess' \
  --include 'uploads/.user.ini' \
  --include 'uploads/.gitkeep' \
  --exclude 'uploads/***' \
  --exclude 'public_html/' \
  --exclude 'ekclick.php' \
  --exclude '_shot_boot.php' \
  --exclude 'cleanup_unused_files.php' \
  --exclude 'bump_asset_version.php' \
  --exclude 'assets/img/banner_*.jpg' \
  --exclude 'assets/img/slide_*.jpg' \
  --exclude 'assets/img/shop_logo_*' \
  --exclude 'assets/img/user_photo_*' \
  --exclude 'assets/img/customers/' \
  "$root/" "$stage/public_html/"

one_click="$out_dir/Aponshop_${version}_FULL_STACK.zip"
folder_zip="$out_dir/aponshop-${version}-clean.zip"
rm -f "$one_click" "$folder_zip" "$out_dir/Aponshop_POS_STACK_UPDATE.zip" \
  "$out_dir/APONSHOP-public_html-fix.zip" "$out_dir/Aponshop_${version}_LIVE.zip"

(
  cd "$stage"
  zip -qr "$one_click" "public_html"
  mkdir -p "aponshop-${version}"
  rsync -a "public_html/" "aponshop-${version}/"
  zip -qr "$folder_zip" "aponshop-${version}"
)

cp -f "$one_click" "$out_dir/Aponshop_POS_STACK_UPDATE.zip"
cp -f "$one_click" "$out_dir/APONSHOP-public_html-fix.zip"

# Flat archive: extract inside the live public_html. No public_html/ wrapper.
live_zip="$out_dir/Aponshop_${version}_LIVE.zip"
(
  cd "$stage/public_html"
  zip -qr "$live_zip" .
)

sha256sum \
  "$one_click" \
  "$out_dir/Aponshop_POS_STACK_UPDATE.zip" \
  "$out_dir/APONSHOP-public_html-fix.zip" \
  "$folder_zip" \
  "$live_zip" \
  | tee "$one_click.sha256"

echo "Created $one_click"
