#!/bin/bash
#
# update Tuefind solr configuration + restart solr
# this might be a useful git hook, e.g. as "post-merge"
#
if [ -z "$TUEFIND_FLAVOUR" ]; then
    echo "TUEFIND_FLAVOUR is not set! Install Tuefind first!"
    exit 1
fi

echo updating solr configuration...
./solr/vufind/biblio/conf/make_symlinks.sh
./solr/vufind/biblio/conf/generate_xml.sh

echo updating solrmarc configuration...
./import/make_marc_local_properties.sh

echo restarting vufind service...
systemctl restart vufind

echo done!

