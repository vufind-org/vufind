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

mkdir -p $DIR/synonyms
for i in de en fr it es pt ru el hans hant; do touch $DIR/synonyms/synonyms_$i.txt; done
touch $DIR/synonyms/synonyms_all.txt
