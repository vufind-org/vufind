#!/bin/bash

# Make sure VUFIND_HOME is set:
if [ -z "$VUFIND_HOME" ]
then
  # set VUFIND_HOME to the absolute path of the directory containing this script
  # https://stackoverflow.com/questions/4774054/reliable-way-for-a-bash-script-to-get-the-full-path-to-itself
  VUFIND_HOME="$(cd "$(dirname "$0")" && pwd -P)"/..
  if [ "$VUFIND_HOME" = /.. ]
  then
    exit 1
  fi
fi

SKIP_OPTIMIZE=0
PREFIX=

while getopts ":s-:" OPT
do
  case $OPT in
    s) SKIP_OPTIMIZE=1;;
    -)
      case "${OPTARG}" in
        id-prefix)
          PREFIX="${!OPTIND}";
          OPTIND=$(( $OPTIND + 1 ))
          ;;
        id-prefix=*)
          PREFIX=${OPTARG#*=}
          ;;
        *)
          echo "Unknown option -- ${OPTARG}" >&2
          ;;
      esac;;
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
  echo "--id-prefix [prefix]: Specify a prefix to prepend to all IDs."
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
    php deletes.php $file flat $2 --id-prefix=$PREFIX
    mv $file $BASEPATH/processed/`basename $file`
  fi
done

if [ "$FOUNDSOME" -eq "1" ]
then
  echo "Optimizing index..."
  php optimize.php
fi