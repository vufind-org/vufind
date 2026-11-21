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
#   Network port for Solr. Defaults to 8983.
#
# SOLR_SECURITY_MANAGER_ENABLED
#   Whether or not to enable the Java security manager (incompatible with
#   AlphaBrowse handler). Defaults to false.
#
# JAVA_HOME
#   Home of Java installation (not directly used by this script, but passed along to
#   the standard Solr control script).
#
# SOLR_ADDITIONAL_START_OPTIONS
#   Additional options to pass to the solr binary at startup.
#
# SOLR_ADDITIONAL_JVM_OPTIONS
#   Additional options to pass to the JVM when launching Solr.
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
  # set VUFIND_HOME to the absolute path of the directory containing this script
  # https://stackoverflow.com/questions/4774054/reliable-way-for-a-bash-script-to-get-the-full-path-to-itself
  VUFIND_HOME="$(cd "$(dirname "$0")" && pwd -P)"
  if [ -z "$VUFIND_HOME" ]
  then
    exit 1
  fi
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
  SOLR_PORT="8983"
fi

if [ -z "$SOLR_SECURITY_MANAGER_ENABLED" ]
then
  export SOLR_SECURITY_MANAGER_ENABLED="false"
fi

if [ -z "$SOLR_ADDITIONAL_START_OPTIONS" ]
then
  SOLR_ADDITIONAL_START_OPTIONS=""
fi

if [ -z "$SOLR_ADDITIONAL_JVM_OPTIONS" ]
then
  SOLR_ADDITIONAL_JVM_OPTIONS=""
fi

export SOLR_LOGS_DIR=$SOLR_LOGS_DIR

# The biblio core needs several jars on its classpath. Solr 10 no longer
# supports <lib/> entries in solrconfig.xml, and the core lib/ directory only
# picks up jar files directly inside it (not subdirectories). We therefore
# (re)generate the lib/ symlinks on every start, from:
#   - the stable jars checked into the VuFind repo, and
#   - the "analysis-extras" module (e.g. ICU4J for the browse normalizers).
# Every symlink in lib/ is regenerated, so the directory is a pure runtime
# artifact (git-ignored) and self-heals across Solr upgrades.
BIBLIO_LIB="$SOLR_HOME/biblio/lib"
mkdir -p "$BIBLIO_LIB"
for link in "$BIBLIO_LIB"/*
do
  [ -L "$link" ] && rm -f "$link"
done
for jar in "$SOLR_HOME/jars"/*.jar \
           "$VUFIND_HOME/import"/solrmarc_core_*.jar \
           "$VUFIND_HOME/import/lib"/marc4j-*.jar
do
  [ -e "$jar" ] && ln -sf "$jar" "$BIBLIO_LIB/"
done
if [ -d "$VUFIND_HOME/solr/vendor/modules/analysis-extras/lib" ]
then
  for jar in "$VUFIND_HOME/solr/vendor/modules/analysis-extras/lib"/*.jar
  do
    ln -sf "$jar" "$BIBLIO_LIB/"
  done
fi

"$SOLR_BIN/solr" "$1" ${SOLR_ADDITIONAL_START_OPTIONS} --port "$SOLR_PORT" --solr-home "$SOLR_HOME" -m "$SOLR_HEAP" --user-managed --jvm-opts "-Ddisable.configEdit=true -Dsolr.log=$SOLR_LOGS_DIR $SOLR_ADDITIONAL_JVM_OPTIONS"
