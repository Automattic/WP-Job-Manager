#!/usr/bin/env bash

# Step 1: Clean and create build folder
rm -rf build
mkdir -p build/wp-job-manager

# Step 2: Copy the current folder to the build folder, excluding undesired directories
# Strip the wp-job-manager/ prefix from exclude.lst so rsync can use it too
# Keep package.json/composer.* — they're needed for build:assets and composer install
sed 's|^wp-job-manager/||' scripts/exclude.lst | grep -v -E '^(package\.json|package-lock\.json|composer\.\*)$' > /tmp/rsync-exclude.lst
rsync -av --progress . build/wp-job-manager --exclude build --exclude vendor --exclude-from /tmp/rsync-exclude.lst
rm -f /tmp/rsync-exclude.lst

# Navigate to build directory
cd build/wp-job-manager

# Step 3: Build assets
npm run build:assets

# Step 4: Run composer install without development dependencies
composer install --no-dev

# Step 5: Zip the entire contents of the build folder into `wp-job-manager.zip` excluding the files from the exclude.lst file
# Navigate one level up, so the zip command includes the build directory content
cd ..
zip -r wp-job-manager.zip wp-job-manager -x@../scripts/exclude.lst

# Step 6: Remove the contents of the build folder except the new zip file
rm -rf wp-job-manager

echo "Build process complete. The wp-job-manager.zip file is ready in the /build folder."
