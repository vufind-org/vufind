#!/bin/bash
#
# Wrapper around import-marc.sh to allow import of authority records.
#

E_BADARGS=65

# No arguments?  Display syntax:
if [ $# -eq 0 ]
then
  echo "    Usage: `basename $0` ./path/to/marc.mrc [properties file]"
  exit $E_BADARGS
fi

##################################################
# Set VUFIND_HOME
##################################################
if [ -z "$VUFIND_HOME" ]
then
  # set VUFIND_HOME to the absolute path of the directory containing this script
  # https://stackoverflow.com/questions/4774054/reliable-way-for-a-bash-script-to-get-the-full-path-to-itself
  export VUFIND_HOME="$(cd "$(dirname "$0")" && pwd -P)"
  if [ -z "$VUFIND_HOME" ]
  then
    exit 1
  fi
fi

# Override some settings in the standard import script:
if [ -f "$VUFIND_LOCAL_DIR/import/import_auth.properties" ]
then
  export PROPERTIES_FILE="$VUFIND_LOCAL_DIR/import/import_auth.properties"
else
  export PROPERTIES_FILE="$VUFIND_HOME/import/import_auth.properties"
fi

# Always use the authority mappings from PROPERTIES_FILE
# if the user specified an override file, add that to the setting.
MAPPINGS_FILENAMES=($(grep '^solr\.indexer\.properties *=' $PROPERTIES_FILE | sed 's/^solr.indexer.properties *= *\(.*\)/\1/p' | tr "," " "))
if [ $# -gt 1 ]
then
  MAPPINGS_FILENAMES+=($2)
fi

MAPPINGS_FILES=""
for MAPPINGS_FILENAME in ${MAPPINGS_FILENAMES[@]}; do
  if [ -n "$MAPPINGS_FILES" ]; then
    MAPPINGS_FILES+=","
  fi

  if [ -f "$VUFIND_LOCAL_DIR/import/$MAPPINGS_FILENAME" ]
  then
    MAPPINGS_FILES+="$VUFIND_LOCAL_DIR/import/$MAPPINGS_FILENAME"
  else
    MAPPINGS_FILES+="$VUFIND_HOME/import/$MAPPINGS_FILENAME"
  fi
done

export SOLRCORE="authority"
export EXTRA_SOLRMARC_SETTINGS="-Dsolr.indexer.properties=$MAPPINGS_FILES"

# Call the standard script:
$VUFIND_HOME/import-marc.sh $1
