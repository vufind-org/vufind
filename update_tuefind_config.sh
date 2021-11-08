#!/bin/bash
#
# update Tuefind solr configuration + restart solr
# this might be a useful git hook, e.g. as "post-merge"
#
if [ -z "$TUEFIND_FLAVOUR" ]; then
    echo "TUEFIND_FLAVOUR is not set! Install Tuefind first!"
    exit 1
fi

DIR="$(dirname $(readlink --canonicalize "$0"))"

echo updating solr configuration...
$DIR/solr/vufind/biblio/conf/make_symlinks.sh
$DIR/solr/vufind/biblio/conf/generate_xml.sh
$DIR/solr/vufind/authority/conf/generate_xml.sh
$DIR/solr/vufind/biblio/conf/touch_synonyms.sh

echo updating solrmarc configuration...
$DIR/import/make_marc_local_properties.sh

echo restarting vufind service...
systemctl restart vufind

echo done!

