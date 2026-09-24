#!/bin/sh
# Build installable zips for WordPress (Appearance → Themes → Upload / Plugins → Upload).
set -e
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/bavar-theme.zip dist/bavar-core.zip
zip -rq dist/bavar-theme.zip bavar-theme -x '*.DS_Store'
zip -rq dist/bavar-core.zip bavar-core -x '*.DS_Store'
ls -la dist
