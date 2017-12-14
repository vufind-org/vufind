#!/bin/bash

if [[ ( $# != 1 ) || ( $1 != "krimdok" && $1 != "ixtheo" ) ]]; then
    echo "Usage: $0 (krimdok | ixtheo)"
    exit 1
fi

rm -f schema_local_fields.xml
rm -f schema_local_types.xml

if [[ $1 == ixtheo ]]; then
    ln -s schema_ixtheo_fields.xml schema_local_fields.xml
    ln -s schema_ixtheo_types.xml schema_local_types.xml
else
    ln -s schema_krimdok_fields.xml schema_local_fields.xml
    ln -s schema_krimdok_types.xml schema_local_types.xml
fi
