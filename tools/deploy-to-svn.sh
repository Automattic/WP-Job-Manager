#!/bin/sh

# ASK INFO
echo "-------------------------------------------"
echo "     	Job Manager RELEASER		 "
echo "-------------------------------------------"
read -p "VERSION: " VERSION
echo "-------------------------------------------"
read -p "PRESS [ENTER] TO RELEASE VERSION "${VERSION}

# VARS
ROOT_PATH=$(pwd)
ROOT_PATH=${ROOT_PATH}"/"
PRODUCT_NAME="WP-Job-Manager"
PRODUCT_NAME_GIT=${PRODUCT_NAME}"-git"
PRODUCT_NAME_SVN=${PRODUCT_NAME}"-svn"
SVN_REPO="http://plugins.svn.wordpress.org/wp-job-manager/"
GIT_REPO="git@github.com:Automattic/WP-Job-Manager.git"

# CHECKOUT SVN DIR IF NOT EXISTS
if [[ ! -d $PRODUCT_NAME_SVN ]];
then
	echo "No SVN directory found, will do a checkout"
	svn checkout $SVN_REPO $PRODUCT_NAME_SVN
fi

# DELETE OLD GIT DIR
rm -Rf $ROOT_PATH$PRODUCT_NAME_GIT

# CLONE GIT DIR
echo "Cloning GIT repo"
git clone $GIT_REPO $PRODUCT_NAME_GIT

# MOVE INTO GIT DIR
cd $ROOT_PATH$PRODUCT_NAME_GIT

if [ -z $( git tag | grep "^$VERSION" ) ]; then
	echo "Tag $TARGET not found in git repository."
	echo "Please try again with a valid tag."
	exit 1
fi

git checkout $TARGET

# REMOVE UNWANTED FILES & FOLDERS
echo "Purging paths included in .svnignore"
# check .svnignore
for file in $( cat "$ROOT_PATH$PRODUCT_NAME_GIT/.svnignore" 2>/dev/null ); do
	rm -rf $ROOT_PATH$PRODUCT_NAME_GIT/$file
done
echo "Done!"

# MOVE INTO SVN DIR
cd $ROOT_PATH$PRODUCT_NAME_SVN

# UPDATE SVN
echo "Updating SVN"
svn update

# DELETE TRUNK
echo "Replacing trunk"
rm -Rf trunk/

# COPY GIT DIR TO TRUNK
cp -R $ROOT_PATH$PRODUCT_NAME_GIT trunk/

# DO THE ADD ALL NOT KNOWN FILES UNIX COMMAND
svn add --force * --auto-props --parents --depth infinity -q

# DO THE REMOVE ALL DELETED FILES UNIX COMMAND
svn rm $( svn status | sed -e '/^!/!d' -e 's/^!//' )

# COPY TRUNK TO TAGS/$VERSION
svn copy trunk tags/${VERSION}

# DO SVN COMMIT
svn status
echo "svn commit -m \"Release "${VERSION}", see readme.txt for changelog.\""

# REMOVE THE GIT DIR
echo "Removing GIT dir"
rm -Rf $ROOT_PATH$PRODUCT_NAME_GIT

# DONE, BYE
echo "RELEASER DONE"
