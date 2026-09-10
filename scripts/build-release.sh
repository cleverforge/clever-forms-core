#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/build"
PACKAGE_DIR="$BUILD_DIR/clever-forms"
ZIP_PATH="$BUILD_DIR/clever-forms.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$PACKAGE_DIR"

rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'build' \
  --exclude 'vendor' \
  --exclude 'node_modules' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'scripts' \
  --exclude 'README.md' \
  --exclude 'CONTRIBUTING.md' \
  --exclude 'SECURITY.md' \
  --exclude 'CHANGELOG.md' \
  "$ROOT_DIR/" "$PACKAGE_DIR/"

cd "$BUILD_DIR"
zip -rq "$(basename "$ZIP_PATH")" clever-forms
printf 'Built %s\n' "$ZIP_PATH"
