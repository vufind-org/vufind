#!/bin/bash
# $Id: index_file.sh 17 2008-06-20 14:40:13Z wayne.graham $
#
# Bash script to start the import of a binary marc file for Solr indexing.
#
# VUFIND_HOME
#   Path to the vufind installation
# JAVA_HOME
#   Path to the java
# INDEX_OPTIONS
#   Options to pass to the JVM
#

#####################################################
# handle the -p option to override properties file
#####################################################
while getopts ":p:" Option
do
  case $Option in
    p) PROPERTIES_FILE=$OPTARG;;
    :)
      echo "argument to '-$OPTARG' is missing" >&2
      exit -1;;
    \?) echo "Unrecognized option '-$OPTARG'" >&2;;
  esac
done
#Decrement the argument pointer so it points to next argument
shift $(($OPTIND - 1))

#####################################################
# Make sure we have the expected number of arguments
#####################################################
E_BADARGS=65
EXPECTED_ARGS=1

if [ $# -ne $EXPECTED_ARGS ]
then
  echo "    Usage: `basename $0` [-p ./path/to/import.properties] ./path/to/marc.mrc"
  exit $E_BADARGS
fi

##################################################
# Set INDEX_OPTIONS
#   Tweak these in accordance to your needs
# Xmx and Xms set the heap size for the Java Virtual Machine
# You may also want to add the following:
# -XX:+UseParallelGC
# -XX:+AggressiveOpts
##################################################
if [ -z "$INDEX_OPTIONS" ]
then
  INDEX_OPTIONS='-Xms512m -Xmx512m -DentityExpansionLimit=0'
fi

##################################################
# Set SOLRCORE
##################################################
if [ ! -z "$SOLRCORE" ]
then
  EXTRA_SOLRMARC_SETTINGS="$EXTRA_SOLRMARC_SETTINGS -Dsolr.core.name=$SOLRCORE"
fi

##################################################
# Set VUFIND_HOME
##################################################
if [ -z "$VUFIND_HOME" ]
then
  # set VUFIND_HOME to the absolute path of the directory containing this script
  # https://stackoverflow.com/questions/4774054/reliable-way-for-a-bash-script-to-get-the-full-path-to-itself
  export VUFIND_HOME="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
fi

if [ -z "$VUFIND_LOCAL_DIR" ]
then
  echo "WARNING: VUFIND_LOCAL_DIR environment variable is not set. Is this intentional?"
fi

#####################################################
# Build java command
#####################################################
if [ "$JAVA_HOME" ]
then
  JAVA="$JAVA_HOME/bin/java"
else
  JAVA="java"
fi

##################################################
# Set properties file if not already provided
##################################################
if [ -z "$PROPERTIES_FILE" ]
then
  if [ -f "$VUFIND_LOCAL_DIR/import/import.properties" ]
  then
    PROPERTIES_FILE="$VUFIND_LOCAL_DIR/import/import.properties"
  else
    PROPERTIES_FILE="$VUFIND_HOME/import/import.properties"
  fi
fi

##################################################
# Set Command Options
##################################################
for i in $VUFIND_HOME/import/solrmarc_core_*.jar; do JAR_FILE="$i"; done

#####################################################
# Verify that JAR_FILE exists
#####################################################
if [ ! -f "$JAR_FILE" ]
then
  echo "Could not find $JAR_FILE.  Make sure VUFIND_HOME is set correctly."
  exit 1
fi

#####################################################
# Normalize target file path to absolute path
#####################################################
MARC_PATH=`dirname $1`
MARC_PATH=`cd $MARC_PATH && pwd`
MARC_FILE=`basename $1`

#####################################################
# Execute Importer
#####################################################

RUN_CMD="$JAVA $INDEX_OPTIONS -Duser.timezone=UTC $EXTRA_SOLRMARC_SETTINGS -jar $JAR_FILE $PROPERTIES_FILE -solrj $VUFIND_HOME/solr/vendor/dist/solrj-lib $MARC_PATH/$MARC_FILE"
echo "Now Importing $1 ..."
# solrmarc writes log messages to stderr, write RUN_CMD to the same place
echo "`date '+%h %d, %H:%M:%S'` $RUN_CMD" >&2
exec $RUN_CMD
