#!/usr/bin/env bash

CHANGED_FILES=`git diff --name-only --diff-filter=ACMR $TRAVIS_COMMIT_RANGE | grep \\\\.php | awk '{print}' ORS=' '`
IGNORE="assets/,docs/,lib/"

if [ "$CHANGED_FILES" != "" ]; then
	echo "Running Code Sniffer."
	./vendor/bin/phpcs --ignore=$IGNORE --encoding=utf-8 -n -p $CHANGED_FILES
fi
