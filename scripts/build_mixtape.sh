#!/usr/bin/env bash

set -e;

SCRIPT_ROOT=`pwd`;
MIXTAPE_TEMP_PATH="$SCRIPT_ROOT/tmp/mt";
MIXTAPE_REPO="https://github.com/Automattic/mixtape/";
MIXTAPEFILE_NAME=".mixtapefile";
MIXTAPE_PATH="${MIXTAPE_PATH-$MIXTAPE_TEMP_PATH}";

# Declare all our reusable functions

show_usage() {
  echo "Builds mixtape for development and plugin deployment";
  echo "Note: Requires git";
  echo "";
  echo "    ./scripts/build_mixtape.sh";
  echo "";
  echo "Assumes $MIXTAPEFILE_NAME is present at project root, generates a stub otherwise";
};

expect_directory() {
  if [ ! -d "$1" ]; then
    echo "Not a directory: $1. Exiting" >&2;
    exit 1;
  fi
};

# Check we have git
command -v git >/dev/null 2>&1 || {
  echo "No Git found. Exiting." >&2;
  show_usage;
  exit 1;
};

if [ "$MIXTAPE_PATH" == "$MIXTAPE_TEMP_PATH" ]; then
  if [ ! -d "$MIXTAPE_PATH" ]; then
    mkdir -p "$MIXTAPE_PATH";
    git clone "$MIXTAPE_REPO" "$MIXTAPE_PATH" || {
      echo "Error cloning mixtape repo: $MIXTAPE_REPO" >&2;
      exit 1;
    }
    cd "$MIXTAPE_PATH" && git checkout master >/dev/null 2>&1;
    if [ "$?" -ne 0 ]; then
      echo "Can't run git checkout command on $MIXTAPE_PATH" >&2;
      exit 1;
    fi
  fi
  cd "$MIXTAPE_PATH" && git fetch 2>&1;
fi

cd "$SCRIPT_ROOT";

expect_directory "$MIXTAPE_PATH";

if [ ! -f "$MIXTAPEFILE_NAME" ]; then
  echo "No $MIXTAPEFILE_NAME found. Generating one (using sha from Mixtape HEAD)";

  echo "sha=$(cd $MIXTAPE_PATH && git rev-parse HEAD)" >> "$MIXTAPEFILE_NAME";
  echo "prefix=YOUR_PREFIX" >> "$MIXTAPEFILE_NAME";
  echo "destination=your/destination" >> "$MIXTAPEFILE_NAME";

  echo "$MIXTAPEFILE_NAME Generated:";
  echo "";
  cat "$MIXTAPEFILE_NAME";
  echo "Amend it with your prefix, sha and destination and rerun this.";
  exit;
fi

cd "$SCRIPT_ROOT";

mt_current_sha="$(cat "$MIXTAPEFILE_NAME" | grep -o 'sha=[^"]*' | sed 's/sha=//')";
mt_current_prefix="$(cat "$MIXTAPEFILE_NAME" | grep -o 'prefix=[^"]*' | sed 's/prefix=//')";
mt_current_destination="$(pwd)/$(cat "$MIXTAPEFILE_NAME" | grep -o 'destination=[^"]*' | sed 's/destination=//')";

echo "============= Building Mixtape =============";
echo "";
echo "SHA         = $mt_current_sha";
echo "PREFIX      = $mt_current_prefix";
echo "DESTINATION = $mt_current_destination";
echo "";

if [ ! -d "$mt_current_destination" ]; then
  mkdir -p "$mt_current_destination"
fi

expect_directory "$mt_current_destination";

cd $MIXTAPE_PATH;
mt_repo_current_sha="$(git rev-parse HEAD)";

if [ "$mt_repo_current_sha" != "$mt_current_sha" ]; then
  echo "Dir";
  git checkout "$mt_current_sha" 2>&1;
  if [ $? -ne 0 ]; then
    echo "Git checkout error" >&2;
    exit 1;
  fi
fi

git diff-index --quiet --cached HEAD >/dev/null 2>&1;

if [ $? -ne 0 ]; then
  echo "Repository (at $MIXTAPE_PATH) is dirty. Please commit or stash the changes. Exiting." >&2;
  exit 1;
fi

echo "Running project script from $MIXTAPE_PATH"
sh "$MIXTAPE_PATH/scripts/new_project.sh" "$mt_current_prefix" "$mt_current_destination";

if [ $? -ne 0 ]; then
  echo "Something went wrong with the file generation, Exiting" >&2;
  git checkout "$mt_repo_current_sha" >/dev/null 2>&1;
  exit 1;
else
  echo "Generation done!";
  git checkout "$mt_repo_current_sha" >/dev/null 2>&1;
fi
