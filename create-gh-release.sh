#!/usr/bin/env bash
# https://gist.github.com/stefanbuck/ce788fee19ab6eb0b4447a85fc99f447
# https://gist.github.com/mcguffin/746afffa0929ca8e2ea2ba8538776742
# https://gist.github.com/mjdietzx/6ec00ebd1223ca1cf87fa0c80e0bf84e
set -e
# Define some useful variables
# wpforms-epfl-payonline
PROJECT_NAME=$(basename ${PWD})
# 0.0.4
VERSION=$(cat wpforms-epfl-payonline.php | grep '* Version:' | awk '{print $3}')
VERSION_NO_DOTS=$(echo $VERSION | sed -e 's/\.//g')
# git@github.com:epfl-idevelop/wpforms-epfl-payonline.git
REPO_REMOTE=$(git config --get remote.origin.url)
# Nicolas Borboën
REPO_OWNER_NAME=$(git config --get user.name)
# ponsfrilus@gmail.com
REPO_OWNER_EMAIL=$(git config --get user.email)
# epfl-idevelop/wpforms-epfl-payonline
REPO_GH_PATH=$(echo $REPO_REMOTE |  cut -d':' -f 2 | cut -d'.' -f 1)
# epfl-idevelop
REPO_ORG_OR_USR=$(echo $REPO_GH_PATH | cut -d'/' -f1)
# wpforms-epfl-payonline
REPO_NAME=$(echo $REPO_GH_PATH | cut -d'/' -f2)
GH_URL=https://github.com/
GH_API=https://api.github.com
# https://help.github.com/en/github/authenticating-to-github/creating-a-personal-access-token-for-the-command-line
GH_ACCESS_TOKEN=$WPEP_GH_TOKEN
# https://github.com/epfl-idevelop/wpforms-epfl-payonline
REPO_HTTP_URL=$GH_URL$REPO_GH_PATH
GH_RELEASE_URL=$GH_API/repos/$REPO_ORG_OR_USR/$REPO_NAME/releases?access_token=$GH_ACCESS_TOKEN
# GET /repos/:owner/:repo/releases/tags/:tag
GH_RELEASE_CHECK_URL=$GH_API/repos/$REPO_ORG_OR_USR/$REPO_NAME/releases/tags/v${VERSION}?access_token=$GH_ACCESS_TOKEN
AUTH="Authorization: token $GH_ACCESS_TOKEN"

function printInfo() {
  echo "--------------------------------------------------------------------------------"
  echo "Version         : $VERSION"
  echo "Version no dots : $VERSION_NO_DOTS"
  echo "Token           : $GH_ACCESS_TOKEN"
  echo "Remote          : $REPO_REMOTE"
  echo "Org             : $REPO_ORG_OR_USR"
  echo "Repo name       : $REPO_NAME"
  echo "URL             : $REPO_HTTP_URL"
  echo "Proj name       : $PROJECT_NAME"
  echo "Owner name      : $REPO_OWNER_NAME"
  echo "Owner email     : $REPO_OWNER_EMAIL"
  echo "--------------------------------------------------------------------------------"
}

# https://gist.github.com/stefanbuck/ce788fee19ab6eb0b4447a85fc99f447
function validate_token () {
  curl -o /dev/null -sH "$AUTH" $GH_RELEASE_URL || { echo "Error: Invalid repo, token or network issue!";  exit 1; }
}

function create_tag () {
  echo -e "\nchecking existing release..."
  # check if tag already exists
  # GET /repos/:owner/:repo/releases/tags/:tag
  RELEASE_ID=$(curl -s $GH_RELEASE_CHECK_URL | jq -r .id)
  if [ -n "$RELEASE_ID" -a "$RELEASE_ID" != 'null' ]
  then
    echo "... release ID: $RELEASE_ID already exists!"
    return
  else 
    echo "... no release found"
    echo -e "\ncreating release..."
  fi

RELEASE_JSON="{\
\"tag_name\":\"v${VERSION}\",\
\"target_commitish\":\"master\",\
\"name\":\"$(printf %q ${PROJECT_NAME}_v${VERSION})\",\
\"body\":\"[CHANGELOG.md](${REPO_HTTP_URL}/blob/master/CHANGELOG.md#v${VERSION_NO_DOTS})\",\
\"draft\":false,\
\"prerelease\":false\
}"

  RELEASE=$(curl --data ${RELEASE_JSON} -s ${GH_RELEASE_URL})
  echo $RELEASE
  RELEASE_ID=$( echo $RELEASE | jq -r .id)
  echo $RELEASE_ID
  RELEASE_ERRORS=$( echo $RELEASE | jq -r .errors)
  echo $RELEASE_ERRORS
  if [ -n "$RELEASE_ERRORS" -a "$RELEASE_ERRORS" != 'null' ]
  then
    echo "Failed to create GitHub release. Exiting with errors: $RELEASE_ERRORS"
    exit 1
  fi

  # exit script if GitHub release was not successfully created
  if [ -z "$RELEASE_ID" ]
  then
    echo "Failed to create GitHub release. Exiting with error."
    exit 1
  else
    echo -e " ... release created!\n"
    echo -e "Release ID      : $RELEASE_ID"
  fi
}


function add_release_file () {
  echo -e "\nchecking existing asset..."
  # check if release already exists
  # GET /repos/:owner/:repo/releases/:release_id/assets
  ASSET_DATA=$(curl -s "https://api.github.com/repos/$REPO_ORG_OR_USR/$REPO_NAME/releases/$RELEASE_ID/assets?access_token=$GH_ACCESS_TOKEN")
  ASSET_ID=$(echo $ASSET_DATA | jq -r '.[0].id')
  echo "ASSET ID: $ASSET_ID"
  if [ -n "$ASSET_ID" -a "$ASSET_ID" != 'null' ]
  then
    echo "... asset ID $ASSET_ID: already exists for this release!"
    echo "... DELETING asset ID $ASSET_ID!"
    ASSET_DELETE=$(curl -s -X DELETE "https://api.github.com/repos/$REPO_ORG_OR_USR/$REPO_NAME/releases/assets/$ASSET_ID?access_token=$GH_ACCESS_TOKEN")
    echo $ASSET_DELETE
    # return
  else 
    echo "... no asset found"
    echo -e "\ncreating asset..."
  fi

  FILENAME=./builds/${PROJECT_NAME}.zip
  GH_ASSET="https://uploads.github.com/repos/$REPO_ORG_OR_USR/$REPO_NAME/releases/$RELEASE_ID/assets?name=$(basename $FILENAME)&access_token=$GH_ACCESS_TOKEN"
  ASSET_INFO=$(curl -s --data-binary @"$FILENAME" -H "Content-Type: application/octet-stream" "$GH_ASSET")
  ASSET_ERRORS=$( echo $ASSET_INFO | jq -r .errors)
  ASSET_DWNLD_URL=$( echo $ASSET_INFO | jq -r .browser_download_url)
  ASSET_ID=$( echo $ASSET_INFO | jq -r .id)
  ASSET_NAME=$( echo $ASSET_INFO | jq -r .name)
  ASSET_SIZE=$( echo $ASSET_INFO | jq -r .size)

  if [ -n "$ASSET_ERRORS" -a "$ASSET_ERRORS" != 'null' ]
  then
    echo "Failed to create GitHub asset. Exiting with errors: $ASSET_ERRORS"
    exit 1
  fi

  if [ -n "$ASSET_ID" -a "$ASSET_ID" != 'null' ]
  then
    echo -e "  ... asset created !\n"
    echo "Asset ID        : $ASSET_ID"
    echo "Asset name      : $ASSET_NAME"
    echo "Asset size      : $ASSET_SIZE"
    echo "Asset Download  : $ASSET_DWNLD_URL"
  else
    echo "Failed to create GitHub asset. Exiting with error."
    exit 1
  fi
}

printInfo
validate_token
create_tag
add_release_file
