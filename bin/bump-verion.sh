#!/usr/bin/env bash
# bin/bump-version.sh 1.0.4
set -euo pipefail
NEW_VERSION="$1"

sed -i "s/^Version: .*/Version: ${NEW_VERSION}/" entrydashboard.php
sed -i "s/Stable tag: .*/Stable tag: ${NEW_VERSION}/" readme.txt

if [ -f package.json ]; then
  npm version "${NEW_VERSION}" --no-git-tag-version --allow-same-version
fi

echo "Bumped to ${NEW_VERSION}. Add a changelog entry in readme.txt, then commit and tag:"
echo "  git commit -am \"Release ${NEW_VERSION}\""
echo "  git tag v${NEW_VERSION}"
echo "  git push && git push origin v${NEW_VERSION}"