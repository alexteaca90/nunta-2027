#!/usr/bin/env bash
# Construiește dist/nunta-2027.zip (folderul din arhivă: nunta-2027/).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
mkdir -p "$TMP/nunta-2027" "$ROOT/dist"
cd "$ROOT"
cp -r nunta-2027.php includes templates assets lib tools README.md CHANGELOG.md "$TMP/nunta-2027/"
rm -f "$ROOT/dist/nunta-2027.zip"
(cd "$TMP" && zip -qr "$ROOT/dist/nunta-2027.zip" nunta-2027 -x '*.DS_Store')
rm -rf "$TMP"
echo "dist/nunta-2027.zip"
