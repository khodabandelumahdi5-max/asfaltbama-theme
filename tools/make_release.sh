#!/bin/sh
# Build release/asfaltbama-child.zip: every theme file except the media in
# content/images that earlier releases already carried (they are imported
# on the site once), plus the article covers (small generated images).
# Usage: tools/make_release.sh <version>
set -e
cd "$(dirname "$0")/.."
VER="$1"
[ -n "$VER" ] || { echo "usage: $0 <version>"; exit 1; }
sed -i "s/define( 'ASFALTBAMA_CHILD_VERSION', '[0-9.]*' );/define( 'ASFALTBAMA_CHILD_VERSION', '$VER' );/" asfaltbama-child/functions.php
sed -i "s/^\tVersion: [0-9.]*$/\tVersion: $VER/" asfaltbama-child/style.css
LIST=$(mktemp)
unzip -Z1 release/asfaltbama-child.zip | grep -v '/$' > "$LIST"
find asfaltbama-child -type f ! -path 'asfaltbama-child/content/images/*' >> "$LIST"
find asfaltbama-child/content/images/covers/intl -type f >> "$LIST" 2>/dev/null || true
find asfaltbama-child/content/images/covers -maxdepth 1 -type f -name "*-cover.webp" >> "$LIST"
# New project photos that the previous release did not carry yet.
find asfaltbama-child/content/images -maxdepth 1 -type f -newer release/asfaltbama-child.zip >> "$LIST"
sort -u "$LIST" | while read -r f; do [ -f "$f" ] && echo "$f"; done > "$LIST.ok"
rm -f dist/asfaltbama-child.zip
mkdir -p dist
zip -q dist/asfaltbama-child.zip -@ < "$LIST.ok"
cp dist/asfaltbama-child.zip release/
rm -f "$LIST" "$LIST.ok"
for f in asfaltbama-child/*.php asfaltbama-child/includes/*.php; do php -l "$f" > /dev/null; done
grep -q "Version: $VER" asfaltbama-child/style.css
unzip -l release/asfaltbama-child.zip | tail -1
