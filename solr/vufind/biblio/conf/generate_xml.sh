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

xmllint --xinclude --format $DIR/schema_${TUEFIND_FLAVOUR}_fields.xml > $DIR/schema_local_fields.xml

# the following command issues a warning, which is why we redirect stdout to /dev/null
# it also exits with a non-zero exit code, which is why we expicitly use exit 0
xmllint --xinclude --format $DIR/schema_${TUEFIND_FLAVOUR}_types.xml  > $DIR/schema_local_types.xml 2> /dev/null
exit 0

