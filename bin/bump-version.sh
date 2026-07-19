#!/usr/bin/env bash
# Usage: ./bin/bump-version.sh 1.0.4

set -euo pipefail

if [ $# -ne 1 ]; then
    echo "Usage: $0 <version>"
    exit 1
fi

NEW_VERSION="$1"

# Update the plugin header version.
sed -Ei \
    "s/^([[:space:]]*\*?[[:space:]]*Version:[[:space:]]*).*/\1${NEW_VERSION}/" \
    entrydashboard.php

# Update the WordPress.org Stable tag.
sed -Ei \
    "s/^(Stable tag:[[:space:]]*).*/\1${NEW_VERSION}/" \
    readme.txt

# Update package.json only if it exists.
if [ -f package.json ]; then
    npm version "${NEW_VERSION}" --no-git-tag-version --allow-same-version
fi

echo "✓ Version bumped to ${NEW_VERSION}"
echo
echo "Next steps:"
echo "  1. Update the changelog in readme.txt"
echo "  2. git add ."
echo "  3. git commit -m \"Release ${NEW_VERSION}\""
echo "  4. git tag v${NEW_VERSION}"
echo "  5. git push origin main"
echo "  6. git push origin v${NEW_VERSION}"