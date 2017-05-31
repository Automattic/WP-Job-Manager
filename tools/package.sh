#!/bin/sh


ROOT_PATH=$(pwd)
ROOT_PATH=${ROOT_PATH}"/"
TMP_DIR=wp-job-manager
TMP_PATH=/tmp/$TMP_DIR/

echo "Copy into temp directory"
cp -R . $TMP_PATH
echo "Done!";

cd $TMP_PATH

echo "Purging paths included in .svnignore"
# check .svnignore
for file in $( cat "$TMP_PATH.svnignore" 2>/dev/null ); do
	rm -rf $TMP_PATH/$file
done
echo "Done!"

echo "Zip it... zip it good!"
cd ${TMP_PATH}..
zip -rq ${ROOT_PATH}wp-job-manager.zip $TMP_DIR
cd $ROOT_PATH
echo "Done!"

echo "Clean up!"
rm -rf $TMP_PATH
echo "Done!"
