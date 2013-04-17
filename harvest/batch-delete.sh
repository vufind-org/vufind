#!/bin/sh

# Make sure VUFIND_HOME is set:
if [ -z "$VUFIND_HOME" ]
then
  echo "Please set the VUFIND_HOME environment variable."
  exit 1
fi

SKIP_OPTIMIZE=0

while getopts ":s" OPT
do
  case $OPT in
    s) SKIP_OPTIMIZE=1;;
    :)
      echo "argument to '-$OPTARG' is missing" >&2
      exit -1;;
    \?) echo "Unrecognized option '-$OPTARG'" >&2;;
  esac
done
# Decrement the argument pointer so it points to next argument
shift $(($OPTIND - 1))

# Make sure command line parameter was included:
if [ -z "$1" ]
then
  echo "This script deletes records based on files created by the OAI-PMH harvester.";
  echo ""
  echo "Usage: `basename $0` [harvest subdirectory] [index type]"
  echo ""
  echo "[harvest subdirectory] is a directory name created by the OAI-PMH harvester."
  echo "This script will search the harvest subdirectories of the directories defined"
  echo "by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables."
  echo ""
  echo "[index type] is optional; defaults to Solr for main bibliographic index, but"
  echo "can be set to SolrAuth for authority index."
  echo ""
  echo "Example: `basename $0` oai_source"
  echo ""
  echo "Options:"
  echo "-s:  Skip optimize operation after importing."
  exit 1
fi

# Check if the path is valid:
BASEPATH="$VUFIND_LOCAL_DIR/harvest/$1"
if [ ! -d $BASEPATH ]
then
  BASEPATH="$VUFIND_HOME/harvest/$1"
fi
if [ ! -d $BASEPATH ]
then
  echo "Directory $BASEPATH does not exist!"
  exit 1
fi

# Create log/processed directories as needed:
if [ ! -d $BASEPATH/processed ]
then
  mkdir $BASEPATH/processed
fi

# Process all the files in the target directory:
FOUNDSOME=0
cd $VUFIND_HOME/util
for file in $BASEPATH/*.delete
do
  if [ -f $file ]
  then
    if [ "$SKIP_OPTIMIZE" -eq "0" ]
    then
      FOUNDSOME=1
    fi
    echo "Processing $file ..."
    php deletes.php $file flat $2
    mv $file $BASEPATH/processed/`basename $file`
  fi
done

if [ "$FOUNDSOME" -eq "1" ]
then
  echo "Optimizing index..."
  php optimize.php
fi