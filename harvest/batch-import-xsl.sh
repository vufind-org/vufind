#!/bin/sh

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
if [ -z "$2" ]
then
  echo "This script processes a batch of harvested XML records using the specified XSL"
  echo "import configuration file."
  echo ""
  echo "Usage: `basename $0` [harvest subdirectory] [properties file]"
  echo ""
  echo "[harvest subdirectory] is a directory name created by the OAI-PMH harvester."
  echo "This script will search the harvest subdirectories of the directories defined"
  echo "by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables."
  echo ""
  echo "[properties file] is a configuration file found in the import subdirectory of"
  echo "either your VUFIND_LOCAL_DIR or VUFIND_HOME directory."
  echo ""
  echo "Example: `basename $0` oai_source ojs.properties"
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

# Flag -- do we need to perform an optimize?
OPTIMIZE=0

# Process all the files in the target directory:
cd $VUFIND_HOME/import
for file in $BASEPATH/*.xml
do
  if [ -f $file ]
  then
    echo "Processing $file ..."
    php import-xsl.php $file $2
    # Only move the file into the "processed" folder if processing was successful:
    if [ "$?" -eq "0" ]
    then
      mv $file $BASEPATH/processed/`basename $file`
      # We processed a file and skip is not set, so we need to optimize later on:
      if [ "$SKIP_OPTIMIZE" -eq "0" ]
      then
        OPTIMIZE=1
      fi
    fi
  fi
done

# Optimize the index now that we are done (if necessary):
if [ "$OPTIMIZE" -eq "1" ]
then
  cd $VUFIND_HOME/util
  echo "Optimizing index..."
  php optimize.php
fi
