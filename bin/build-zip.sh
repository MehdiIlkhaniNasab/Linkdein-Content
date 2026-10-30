#!/usr/bin/env bash
# Build badban.zip (installable from WordPress › Plugins › Add New › Upload).
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(grep -oP "BDB_VERSION\s*=\s*'\K[^']+" badban/badban.php)
OUT="badban-${VERSION}.zip"
rm -f "$OUT"
zip -rq "$OUT" badban -x '*.DS_Store' '*/.git*'
echo "Built $OUT"
