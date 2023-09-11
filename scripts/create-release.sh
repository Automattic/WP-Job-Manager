#!/usr/bin/env bash


if [ -z "$1" ]; then
	echo "Create release tag and GH release. Triggers deploy to WordPress.org"
	echo ""
	echo "Usage: npm run release:create [PR]"
	exit 1
fi

node scripts/release-create.mjs wp-job-manager $1
