#!/usr/bin/env bash
# Most part taken from https://github.com/nouchka/faas-shell-semver/blob/master/increment_version.sh
set -e

# Find the current version
INIT_VERSION=$(cat wpforms-epfl-payonline.php | grep '* Version:' | awk '{print $3}')

# Parse the version number
VERSION_ARRAY=( ${INIT_VERSION//./ } )
if [ ${#VERSION_ARRAY[@]} -ne 3 ]
then
  echo "Please check the version number"
  exit 1
fi

# Check if the version get any special, e.g. -alpha or -rc
VERSION_SPECIAL=(${VERSION_ARRAY[2]//-/ })
if [ ! -z ${VERSION_SPECIAL[1]} ]
then
  echo "This script does not handle special, '-${VERSION_SPECIAL[1]}' will be removed"
  VERSION_ARRAY[2]=${VERSION_SPECIAL[0]} 
fi

# Upgrade the version, according to args
while getopts ":Mmp" OPTIONS
do
  case $OPTIONS in
    M ) major=true;;
    m ) minor=true;;
    p ) patch=true;;
  esac
done

# Increment version numbers as requested.
if [ ! -z $major ]
then
  ((++VERSION_ARRAY[0]))
  VERSION_ARRAY[1]=0
  VERSION_ARRAY[2]=0
fi

if [ ! -z $minor ]
then
  ((++VERSION_ARRAY[1]))
  VERSION_ARRAY[2]=0
fi

if [ ! -z $patch ]
then
  ((++VERSION_ARRAY[2]))
fi

NEWVERSION="${VERSION_ARRAY[0]}.${VERSION_ARRAY[1]}.${VERSION_ARRAY[2]}"
echo -e "You are about to change the version from \e[34m$INIT_VERSION\e[39m to \033[1m\e[93m$NEWVERSION\e[39m\033[0m"

read -p "Are you sure? [Yy]: " -n 1 -r
echo    # (optional) move to a new line
if [[ $REPLY =~ ^[Yy]$ ]]
then
  # Find the line in the WordPress header
  LINE=$(cat wpforms-epfl-payonline.php | grep '* Version:')
  # Replace the version in the specified line
  sed -i.bak "/$LINE/ s/$INIT_VERSION/$NEWVERSION/g" wpforms-epfl-payonline.php

  # Find the line in the file
  LINE=$(cat wpforms-epfl-payonline.php | grep 'WPFORMS_EPFL_PAYONLINE_VERSION')
  # Replace the version in the specified line
  sed -i.bak "/$LINE/ s/$INIT_VERSION/$NEWVERSION/g" wpforms-epfl-payonline.php
else 
  echo "Aborted"
fi