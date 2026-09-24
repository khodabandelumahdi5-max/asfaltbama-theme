#!/usr/bin/env sh
# Build installable zips into ../dist (upload via Plugins/Themes › Add New › Upload).
set -e
cd "$(dirname "$0")/.."
mkdir -p ../dist
rm -f ../dist/yadak-core.zip ../dist/yadak-child.zip
(cd plugins && zip -qr ../../dist/yadak-core.zip yadak-core)
(cd themes && zip -qr ../../dist/yadak-child.zip yadak-child)
ls -la ../dist
