#!/usr/bin/env bash
# Builds the wordpress.org release copy in build/readwell-bar and build/readwell-bar.zip,
# leaving out the top-level entries listed in .distignore.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf build/readwell-bar build/readwell-bar.zip
mkdir -p build/readwell-bar
shopt -s dotglob
for entry in *; do
	if ! grep -qxF "/$entry" .distignore; then
		cp -r "$entry" build/readwell-bar/
	fi
done
(cd build && zip -qr readwell-bar.zip readwell-bar)
echo "Built build/readwell-bar ($(find build/readwell-bar -type f | wc -l) files) and build/readwell-bar.zip"
