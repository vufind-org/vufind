#!/bin/bash
set -e

# For development use: Mimic the VuFind repository `solr/vendor` folder location so the biblio core
# can load the necessary libraries.
mkdir -p /opt/solr/server/solr/vendor && ln -sf /opt/solr/contrib /opt/solr/server/solr/vendor/