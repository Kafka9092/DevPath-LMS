#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-6.0.5}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT="${DIR}/rdkafka-${VERSION}.tar.gz"
URL="https://github.com/arnaud-lb/php-rdkafka/archive/refs/tags/${VERSION}.tar.gz"

echo "Downloading php-rdkafka ${VERSION}..."
curl -fsSL --connect-timeout 30 --retry 3 --retry-delay 5 -o "${OUT}" "${URL}"
echo "Saved: ${OUT}"
ls -lh "${OUT}"
