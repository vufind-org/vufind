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
if [ ! -d "$HARVEST_DIR" ]
then
  HARVEST_DIR="$VUFIND_HOME/harvest"
fi

if [ -z "$MAX_BATCH_COUNT" ]
then
  MAX_BATCH_COUNT=10
fi

BASEPATH_UNDER_HARVEST=true
LOGGING=true
MOVE_DATA=true

function usage {
cat <<EOF
This script processes a batch of harvested MARC records.

Usage: $(basename "$0") [-dhmz] [-p properties_file] _harvest_subdirectory_

_harvest_subdirectory_ is a directory name created by the OAI-PMH harvester.
This script will search the harvest subdirectories of the directories defined
by the VUFIND_LOCAL_DIR or VUFIND_HOME environment variables.

Example: $(basename "$0") oai_source

Options:
-d:  Use the directory path as-is, do not append it to $HARVEST_DIR.
     Useful for non-OAI batch loading.
-h:  Print this message
-m:  Do not move the data files after importing.
-p:  Used specified SolrMarc configuration properties file
-x:  Maximum number of files to send in batches to import-marc.sh (default is MAX_BATCH_COUNT or 10)
-z:  No logging.
EOF
}

while getopts ":dhmp:x:z" OPT
do
  case $OPT in
    d) BASEPATH_UNDER_HARVEST=false;;
    h) usage;
       exit 0;;
    m) MOVE_DATA=false;;
    p) PROPERTIES_FILE="$OPTARG"; export PROPERTIES_FILE;;
    x) MAX_BATCH_COUNT="$OPTARG";;
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
if [ -z "$1" ]
then
  usage
  exit 1
fi

# Check MAX_BATCH_COUNT is a positive integer
if ! [[ "$MAX_BATCH_COUNT" =~ ^[1-9][0-9]*$ ]]
then
  echo "MAX_BATCH_COUNT (option -x) is not a positive integer: \"$MAX_BATCH_COUNT\""
  exit 1
fi

# Set up BASEPATH and check if the path is valid:
if [ $BASEPATH_UNDER_HARVEST == false ]
then
  BASEPATH=$1
else
  BASEPATH="$HARVEST_DIR/$1"
fi
if [ ! -d "$BASEPATH" ]
then
  echo "Directory $BASEPATH does not exist!"
  exit 1
fi

# Create log/processed directories as needed:
if [ $LOGGING == true ]
then
  if [ ! -d "$BASEPATH"/log ]
  then
    mkdir "$BASEPATH"/log
  fi
fi
if [ $MOVE_DATA == true ]
then
  if [ ! -d "$BASEPATH"/processed ]
  then
    mkdir "$BASEPATH"/processed
  fi
fi

# The log() function can be redefined to suit a variety of logging needs
# Positional parameters must be consistent:
# $1 = name of the first file being imported
if [ $LOGGING == false ]
then
  function log {
    cat - > /dev/null
  }
else
  function log {
    local FILES=$@
    local LOGFILE
    if [ $# -eq 1 ]
    then
      LOGFILE="$BASEPATH/log/$(basename "$1").log" > "$LOGFILE"
    else
      LOGFILE="$BASEPATH/log/$(basename "$1")_and_more.log"
      echo -e "This log is for the following files: \n$FILES\n" > "$LOGFILE"
    fi
    cat -u - >> "$LOGFILE"
  }
fi

# Collect all matching files into an array using a null-separated list
mapfile -d '' files < <(find -L "$BASEPATH" -maxdepth 1 \( -iname "*.xml" -o -iname "*.mrc" -o -iname "*.marc" \) -type f -print0 | sort -z)
total_files=${#files[@]}

# Iterate over the files in batches
for ((i = 0; i < total_files; i += MAX_BATCH_COUNT)); do
    # Slice off a batch of files
    batch=("${files[@]:i:MAX_BATCH_COUNT}")

    # Execute the import command with the batch of files
    if "$VUFIND_HOME"/import-marc.sh "${batch[@]}" 2> >(log "${batch[@]}"); then
        if [ "$MOVE_DATA" == true ]; then
            for file in "${batch[@]}"; do
                mv "$file" "$BASEPATH/processed/$(basename "$file")"
            done
        fi
    else
        echo "Failed to process batch starting with ${batch[0]}" >&2
    fi
done
