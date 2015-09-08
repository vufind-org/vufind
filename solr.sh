#!/bin/sh

# Set VUFIND_HOME
if [ -z "$VUFIND_HOME" ]
then
  VUFIND_HOME=`basedir $0`
fi

if [ -z "$SOLR_HOME" ]
then
  SOLR_HOME="$VUFIND_HOME/solr/vufind"
fi

$VUFIND_HOME/solr/vendor/bin/solr $1 -p 8080 -s $SOLR_HOME
