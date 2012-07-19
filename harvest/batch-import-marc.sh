#!/bin/sh

# Make sure VUFIND_HOME is set:
if [ -z "$VUFIND_HOME" ]
then
  echo "Please set the VUFIND_HOME environment variable."
  exit 1
fi

# Make sure command line parameter was included:
if [ -z "$1" ]
then
  echo "This script processes a batch of harvested MARC records."
  echo ""
  echo "Usage: `basename $0` [harvest subdirectory]"
  echo ""
  echo "[harvest subdirectory] is a directory name created by the OAI-PMH harvester."
  echo "This script will search the harvest subdirectories of the directories defined"
  echo "by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables."
  echo ""
  echo "Example: `basename $0` oai_source"
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
    echo "Processing $file ..."
    $VUFIND_HOME/import-marc.sh $file > $BASEPATH/log/`basename $file`.log
    mv $file $BASEPATH/processed/`basename $file`
  fi
done
