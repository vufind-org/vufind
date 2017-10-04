#!/bin/bash
#
# Configuration script for tufind, to activate a special instance
#
# This script generates files, dependent on the instance configuration
# - solr configuration (e.g. active plugins)
# - solr schema
# - solrmarc configuration
# - additional customized scripts if needed, like index-alphabetic-browse.sh
#
# Also generates a tufind.instance file with the current active configuration.
# Useful e.g. to use it in a git post-merge hook, to automatically update configurations
# if e.g. the solr schema has changed.

TUFIND_INSTANCE=0;

# check arguments
if [ "$1" == "ixtheo" ]; then
    TUFIND_INSTANCE="ixtheo"
fi

if [ "$1" == "krimdok" ]; then
    TUFIND_INSTANCE="krimdok"
fi

# exit if something not set
if [ "$TUFIND_INSTANCE" == 0 ]; then
    echo "ERROR: first parameter must be ixtheo or krimdok"
    exit 1
fi

# create a symlink. usage is like ln -s <link> <target>
function createSymlink {
    DIR_CURRENT=$PWD
    DIR_LN_LINK=$(dirname "$1")
    DIR_LN_TARGET=$(dirname "$2")
    FILE_LN_LINK=$(basename "$1")
    FILE_LN_TARGET=$(basename "$2")

    if [ -e $1 ]; then
        echo "creating symlink $1 "$FILE_LN_TARGET" (overwriting existing file)"
    else
        echo "creating symlink $1 $2"
    fi

    cd $DIR_LN_LINK
    if [ $DIR_LN_LINK == $DIR_LN_TARGET ]; then
        ln -fs "$FILE_LN_TARGET" "$FILE_LN_LINK"
    else
        ln -fs "$2" "$FILE_LN_LINK"
    fi
    cd $DIR_CURRENT
}

# generate properties file
# (e.g. merging marc_tufind.properties and marc_$TUFIND_INSTANCE.properties into marc_local.properties)
function generateProperties {
    FILE_SOURCE_1=$(basename "$1")
    FILE_SOURCE_2=$(basename "$2")

    echo "generating $3 from $FILE_SOURCE_1 and $FILE_SOURCE_2"
    cat "$1" > "$3"
    cat "$2" >> "$3"
}

function generateXml {
    DIR_SOURCE=$(dirname "$1")
    DIR_TARGET=$(dirname "$2")
    FILE_SOURCE=$(basename "$1")
    FILE_TARGET=$(basename "$2")

    echo "generating $2 from $FILE_SOURCE"
    echo "  (note: if you get XInclude errors, these may be ignored => fallback IS defined and working!!!)"
    xmllint --xinclude --format "$1" > "$2"
}

# this function is used to ignore git changes.
# the file does not occur in "git status" anymore,
# but if the file in the remote repository changes,
# there will be a merge conflict anyway.
function gitAssumeUnchanged {
    git update-index --assume-unchanged "$1"
}

# check if a custom file exists
# if yes => create symlink from original file + git assume unchanged
# if no => restore original file from git
function useCustomFileIfExists {
    FILE_DEFAULT="$1"
    FILE_CUSTOM="$2"

    if [ -e $FILE_CUSTOM ]; then
        gitAssumeUnchanged $FILE_DEFAULT
        createSymlink $FILE_DEFAULT $FILE_CUSTOM
    else
        echo "restoring $FILE_DEFAULT from git"
        git checkout $FILE_DEFAULT
    fi
}

echo "Starting configuration of $TUFIND_INSTANCE"
echo

# dirs
DIR_SOLR_CONF="$VUFIND_HOME/solr/vufind/biblio/conf"
DIR_SOLRMARC_CONF="$VUFIND_HOME/import"

# schema
FILE_SOLR_SCHEMA_LOCAL_FIELDS="$DIR_SOLR_CONF/schema_local_fields.xml"
FILE_SOLR_SCHEMA_LOCAL_TYPES="$DIR_SOLR_CONF/schema_local_types.xml"
FILE_SOLR_SCHEMA_CUSTOM_FIELDS="$DIR_SOLR_CONF/schema_"$TUFIND_INSTANCE"_fields.xml"
FILE_SOLR_SCHEMA_CUSTOM_TYPES="$DIR_SOLR_CONF/schema_"$TUFIND_INSTANCE"_types.xml"

generateXml $FILE_SOLR_SCHEMA_CUSTOM_FIELDS $FILE_SOLR_SCHEMA_LOCAL_FIELDS
generateXml $FILE_SOLR_SCHEMA_CUSTOM_TYPES $FILE_SOLR_SCHEMA_LOCAL_TYPES

# solrconfig
FILE_SOLR_CONFIG_LOCAL="$DIR_SOLR_CONF/solrconfig.xml"
FILE_SOLR_CONFIG_CUSTOM="$DIR_SOLR_CONF/solrconfig_"$TUFIND_INSTANCE".xml"

gitAssumeUnchanged $FILE_SOLR_CONFIG_LOCAL
createSymlink $FILE_SOLR_CONFIG_LOCAL $FILE_SOLR_CONFIG_CUSTOM

# solrmarc (marc_local.properties)
FILE_MARC_LOCAL="$DIR_SOLRMARC_CONF/marc_local.properties"
FILE_MARC_TUFIND="$DIR_SOLRMARC_CONF/marc_tufind.properties"
FILE_MARC_CUSTOM="$DIR_SOLRMARC_CONF/marc_"$TUFIND_INSTANCE".properties"

gitAssumeUnchanged $FILE_MARC_LOCAL
generateProperties $FILE_MARC_TUFIND $FILE_MARC_CUSTOM $FILE_MARC_LOCAL

# index alphabetical browse (only if special script for current instance exists)
# (also checking browse indexing & browse handler jar files)
FILE_ALPHABROWSE="$VUFIND_HOME/index-alphabetic-browse.sh"
FILE_ALPHABROWSE_CUSTOM="$VUFIND_HOME/index-alphabetic-browse_"$TUFIND_INSTANCE".sh"
useCustomFileIfExists $FILE_ALPHABROWSE $FILE_ALPHABROWSE_CUSTOM

FILE_BROWSE_INDEXING="$VUFIND_HOME/import/browse-indexing.jar"
FILE_BROWSE_INDEXING_CUSTOM="$VUFIND_HOME/import/browse-indexing_"$TUFIND_INSTANCE".jar"
useCustomFileIfExists $FILE_BROWSE_INDEXING $FILE_BROWSE_INDEXING_CUSTOM

FILE_BROWSE_HANDLER="$VUFIND_HOME/solr/vufind/jars/browse-handler.jar"
FILE_BROWSE_HANDLER_CUSTOM="$VUFIND_HOME/solr/vufind/jars/browse-handler_"$TUFIND_INSTANCE".jar"
useCustomFileIfExists $FILE_BROWSE_HANDLER $FILE_BROWSE_HANDLER_CUSTOM

# write configured instance to file for git hook
echo
echo $TUFIND_INSTANCE > "$VUFIND_HOME/tufind.instance"
echo $TUFIND_INSTANCE" successfully configured!"
