#!/bin/bash

# Make sure VUFIND_HOME is set:
if [ -z "$VUFIND_HOME" ]
then
  # set VUFIND_HOME to the absolute path of the directory containing this script
  # https://stackoverflow.com/questions/4774054/reliable-way-for-a-bash-script-to-get-the-full-path-to-itself
  export VUFIND_HOME="$(cd "$(dirname "$0")" && pwd -P)"/..
  if [ "$VUFIND_HOME" = /.. ]
  then
    exit 1
  fi
fi

# Find harvest directory for future use
HARVEST_DIR="$VUFIND_LOCAL_DIR/harvest"
if [ ! -d $HARVEST_DIR ]
then
  HARVEST_DIR="$VUFIND_HOME/harvest"
fi

BASEPATH_UNDER_HARVEST=true
LOGGING=true
MOVE_DATA=true

function usage {
cat <<EOF
This script processes a batch of harvested authority records.

Usage: $(basename $0) [-dhmz] _harvest_subdirectory_ _SolrMarc_properties_file_

_harvest_subdirectory_ is a directory name created by the OAI-PMH harvester.
This script will search the harvest subdirectories of the directories defined
by the VUFIND_LOCAL_DIR or VUFIND_HOME environment variables.

Example: $(basename $0) lcnaf marc_lcnaf.properties

Options:
-d:  Use the directory path as-is, do not append it to $HARVEST_DIR.
     Useful for non-OAI batch loading.
-h:  Print this message
-m:  Do not move the data files after importing.
-z:  No logging.
EOF
}

while getopts ":dhmz" OPT
do
  case $OPT in
    d) BASEPATH_UNDER_HARVEST=false;;
    h) usage; 
       exit 0;;
    m) MOVE_DATA=false;;
    z) LOGGING=false;;
    :)
      echo "argument to '-$OPTARG' is missing" >&2
      exit -1;;
    \?) echo "Unrecognized option '-$OPTARG'" >&2;;
  esac
done
#Decrement the argument pointer so it points to next argument
shift $(($OPTIND - 1))

# Make sure command line parameter was included:
if [ -z "$2" ]
then
  usage
  exit 1
fi

# Set up BASEPATH and check if the path is valid:
if [ $BASEPATH_UNDER_HARVEST == false ]
then
  BASEPATH=$1
else
  BASEPATH="$HARVEST_DIR/$1"
fi
if [ ! -d $BASEPATH ]
then
  echo "Directory $BASEPATH does not exist!"
  exit 1
fi

# Create log/processed directories as needed:
if [ $LOGGING == true ]
then
  if [ ! -d $BASEPATH/log ]
  then
    mkdir $BASEPATH/log
  fi
fi
if [ $MOVE_DATA == true ]
then
  if [ ! -d $BASEPATH/processed ]
  then
    mkdir $BASEPATH/processed
  fi
fi

# The log() function can be redefined to suit a variety of logging needs
# Positional parameters must be consistent:
# $1 = name of the file being imported
if [ $LOGGING == false ]
then
  function log {
    cat - > /dev/null
  }
else
  function log {
    local FILE=$1
    cat -u - > $BASEPATH/log/`basename $FILE`.log
  }
fi

# Process all the files in the target directory:
find -L $BASEPATH -maxdepth 1 \( -iname "*.xml" -o -iname "*.mrc" -o -iname "*.marc" \) -type f -print0 | sort -z | \
  while read -d $'\0' file
do
  # Logging output handled by log() function
  $VUFIND_HOME/import-marc-auth.sh $file $2 2> >(log $file)
  if [ "$?" -eq "0" ] && [ $MOVE_DATA == true ]
  then
    mv $file $BASEPATH/processed/`basename $file`
  fi
done
