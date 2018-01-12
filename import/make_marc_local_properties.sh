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

