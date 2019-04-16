#!/bin/bash
#
# This tool is necessary if you wanna have cronjobs for multiple instances on the same machine.
UTIL_DIR="$(dirname $(readlink --canonicalize "$0"))"

if [ -z "$1" ]; then
    echo "Usage: $0 <ixtheo/relbib/krimdok>"
else
    export VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR/../$1"
    php $UTIL_DIR/sitemap.php
fi