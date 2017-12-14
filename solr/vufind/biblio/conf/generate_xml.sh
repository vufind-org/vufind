#!/bin/bash

if [[ ( $# != 1 ) || ( $1 != "krimdok" && $1 != "ixtheo" ) ]]; then
    echo "Usage: $0 (krimdok | ixtheo)"
    exit 1
fi

if [[ $1 == ixtheo ]]; then
    xmllint --xinclude --format schema_ixtheo_fields.xml > schema_local_fields.xml 2> /dev/null
    xmllint --xinclude --format schema_ixtheo_types.xml  > schema_local_types.xml  2> /dev/null
else
    xmllint --xinclude --format schema_krimdok_fields.xml > schema_local_fields.xml 2> /dev/null
    xmllint --xinclude --format schema_krimdok_types.xml  > schema_local_types.xml  2> /dev/null
fi
