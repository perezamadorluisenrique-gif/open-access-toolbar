#!/usr/bin/env bash
# Builds the wordpress.org release copy in build/open-access-toolbar and build/open-access-toolbar.zip,
# leaving out the top-level entries listed in .distignore.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf build/open-access-toolbar build/open-access-toolbar.zip
mkdir -p build/open-access-toolbar
shopt -s dotglob
for entry in *; do
	if ! grep -qxF "/$entry" .distignore; then
		cp -r "$entry" build/open-access-toolbar/
	fi
done
(cd build && zip -qr open-access-toolbar.zip open-access-toolbar)
echo "Built build/open-access-toolbar ($(find build/open-access-toolbar -type f | wc -l) files) and build/open-access-toolbar.zip"
