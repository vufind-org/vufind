#!/bin/bash

if [[ ( $# != 1 ) || ( $1 != "krimdok" && $1 != "ixtheo" ) ]]; then
    echo "Usage: $0 (krimdok | ixtheo)"
    exit 1
fi

if [[ $1 == krimdok ]]; then
    cat marc_tuefind.properties marc_krimdok.properties > marc_local.properties
else
    cat marc_tuefind.properties marc_ixtheo.properties > marc_local.properties
fi
