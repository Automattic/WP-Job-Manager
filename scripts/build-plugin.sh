#!/usr/bin/env bash

# Fail loudly on any error or unset variable so a broken build never produces a zip.
set -euo pipefail

# Step 1: Clean and create build folder
rm -rf build
mkdir -p build/wp-job-manager

# Step 2: Build assets at the repo root.
# wp-scripts needs webpack.config.js, node_modules and the assets/{js,css} sources,
# none of which are copied into the build folder (they're excluded from the release).
# Building here ensures assets/dist is freshly generated before it is staged.
npm run build:assets

# Step 3: Verify the asset build actually produced output before continuing.
# wp-scripts exits 0 even when it builds nothing, so check explicitly.
if ! ls assets/dist/css/*.css >/dev/null 2>&1; then
	echo "ERROR: asset build produced no assets/dist/css/*.css; aborting release build." >&2
	exit 1
fi

# Step 4: Copy the current folder to the build folder, excluding undesired directories.
# Strip the wp-job-manager/ prefix from exclude.lst so rsync can use it too.
# Keep package.json/composer.* — they're needed for composer install.
# The freshly built assets/dist is copied as part of this step.
sed 's|^wp-job-manager/||' scripts/exclude.lst | grep -v -E '^(package\.json|package-lock\.json|composer\.\*)$' > /tmp/rsync-exclude.lst
rsync -av --progress . build/wp-job-manager --exclude build --exclude vendor --exclude-from /tmp/rsync-exclude.lst
rm -f /tmp/rsync-exclude.lst

# Navigate to build directory
cd build/wp-job-manager

# Step 5: Run composer install without development dependencies
composer install --no-dev

# Step 6: Verify the staged plugin contains the built assets before zipping.
if ! ls assets/dist/css/*.css >/dev/null 2>&1; then
	echo "ERROR: assets/dist/css/*.css missing from staged plugin; aborting release build." >&2
	exit 1
fi

# Step 7: Zip the entire contents of the build folder into `wp-job-manager.zip` excluding the files from the exclude.lst file
# Navigate one level up, so the zip command includes the build directory content
cd ..
zip -r wp-job-manager.zip wp-job-manager -x@../scripts/exclude.lst

# Step 8: Remove the contents of the build folder except the new zip file
rm -rf wp-job-manager

echo "Build process complete. The wp-job-manager.zip file is ready in the /build folder."
