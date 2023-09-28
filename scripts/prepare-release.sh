#!/usr/bin/env bash


if [ -z "$1" ]; then
	echo "Prepare a new version for release."
	echo ""
	echo "Usage: npm run release [version]"
	exit 1
fi

node scripts/prepare-release.mjs wp-job-manager $1
