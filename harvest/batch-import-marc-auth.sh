#!/bin/sh

# Make sure VUFIND_HOME is set:
if [ -z "$VUFIND_HOME" ]
then
  echo "Please set the VUFIND_HOME environment variable."
  exit 1
fi

# Find harvest directory for future use
HARVEST_DIR="$VUFIND_LOCAL_DIR/harvest"
if [ ! -d $HARVEST_DIR ]
then
  HARVEST_DIR="$VUFIND_HOME/harvest"
fi

BASEPATH_UNDER_HARVEST=true
MOVE_DATA=true

while getopts ":dm" OPT
do
  case $OPT in
    d) BASEPATH_UNDER_HARVEST=false;;
    m) MOVE_DATA=false;;
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
  echo "This script processes a batch of harvested authority records."
  echo ""
  echo "Usage: `basename $0` [-d] [-m] [harvest subdirectory] [SolrMarc properties file]"
  echo ""
  echo "[harvest subdirectory] is a directory name created by the OAI-PMH harvester."
  echo "This script will search the harvest subdirectories of the directories defined"
  echo "by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables."
  echo ""
  echo "Example: `basename $0` lcnaf marc_lcnaf.properties"
  echo ""
  echo "Options:"
  echo "-d:  Use the directory path as-is, do not append it to $HARVEST_DIR."
  echo "     Useful for non-OAI batch loading."
  echo "-m:  Do not move the data files after importing."
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
if [ ! -d $BASEPATH/log ]
then
  mkdir $BASEPATH/log
fi
if [ ! -d $BASEPATH/processed ]
then
  mkdir $BASEPATH/processed
fi

# Process all the files in the target directory:
for file in $BASEPATH/*.xml $BASEPATH/*.mrc
do
  if [ -f $file ]
  then
    # Capture solrmarc output to log
    $VUFIND_HOME/import-marc-auth.sh $file $2 2> $BASEPATH/log/`basename $file`.log
    if [ $MOVE_DATA == true ]
    then
      mv $file $BASEPATH/processed/`basename $file`
    fi
  fi
done
