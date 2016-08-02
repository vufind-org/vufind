#!/bin/sh
#
# Startup script for the VuFind Jetty Server under *nix systems
#
# Configuration variables
#
# VUFIND_HOME
#   Home of the VuFind installation.
#
# SOLR_BIN
#   Home of the Solr executable scripts.
#
# SOLR_HEAP
#   Size of the Solr heap (i.e. 512M, 2G, etc.). Defaults to 1G.
#
# SOLR_HOME
#   Home of the Solr indexes and configurations.
#
# SOLR_PORT
#   Network port for Solr. Defaults to 8080.
#
# JAVA_HOME
#   Home of Java installation (not directly used by this script, but passed along to
#   the standard Solr control script).
#

usage()
{
    echo "Usage: $0 {start|stop|restart|status}"
    exit 1
}


[ $# -gt 0 ] || usage

# Set VUFIND_HOME
if [ -z "$VUFIND_HOME" ]
then
  VUFIND_HOME=$(dirname "$0")
fi

if [ -z "$SOLR_HOME" ]
then
  SOLR_HOME="$VUFIND_HOME/solr/vufind"
fi

if [ -z "$SOLR_LOGS_DIR" ]
then
  SOLR_LOGS_DIR="$SOLR_HOME/logs"
fi

if [ -z "$SOLR_BIN" ]
then
  SOLR_BIN="$VUFIND_HOME/solr/vendor/bin"
fi

if [ -z "$SOLR_HEAP" ]
then
  SOLR_HEAP="1G"
fi

if [ -z "$SOLR_PORT" ]
then
  SOLR_PORT="8080"
fi

export SOLR_LOGS_DIR=$SOLR_LOGS_DIR
"$SOLR_BIN/solr" "$1" -p "$SOLR_PORT" -s "$SOLR_HOME" -m "$SOLR_HEAP" -a "-Dsolr.log=$SOLR_LOGS_DIR"
