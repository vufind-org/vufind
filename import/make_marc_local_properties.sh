#!/bin/bash

if [ -z "$TUEFIND_FLAVOUR" ]; then
    if [[ ( $# != 1 ) || ( $1 != "krimdok" && $1 != "ixtheo" ) ]]; then
        echo "Usage: $0 (krimdok | ixtheo)"
        exit 1
    else
        TUEFIND_FLAVOUR=$1
    fi
fi

DIR="$(dirname $(readlink --canonicalize "$0"))"

cat $DIR/marc_tuefind.properties $DIR/marc_${TUEFIND_FLAVOUR}.properties > $DIR/marc_local.properties
cat $DIR/marc_auth_tuefind.properties $DIR/marc_auth_${TUEFIND_FLAVOUR}.properties > $DIR/marc_auth_local.properties
git update-index --assume-unchanged $DIR/marc_local.properties

# The following line is not needed because marc_auth_local.properties is not pushed to git in vufind-org
#git update-index --assume-unchanged $DIR/marc_auth_local.properties
