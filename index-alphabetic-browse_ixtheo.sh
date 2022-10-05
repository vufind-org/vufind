#!/bin/bash
set -o errexit

# Setup ramdisk to speed up things

TMP_RAMDISK_DIR="/tmp/ramdisk"


trap ExitHandler EXIT
trap ExitHandler SIGINT

function ExitHandler {
   ShutdownRamdisk
}

function ShutdownRamdisk() {
    if mountpoint --quiet ${TMP_RAMDISK_DIR}; then
        umount ${TMP_RAMDISK_DIR}
    fi
}


#####################################################
# Build java command
#####################################################
if [ "$JAVA_HOME" ]
then
  JAVA="$JAVA_HOME/bin/java"
else
  JAVA="java"
fi

if [ -z "$VUFIND_HOME" ]
then
  VUFIND_HOME=`dirname $0`
fi

if [ -z "$SOLR_HOME" ]
then
  SOLR_HOME="$VUFIND_HOME/solr/vufind"
fi

cd "`dirname $0`/import"
CLASSPATH="browse-indexing.jar:${SOLR_HOME}/jars/*:${SOLR_HOME}/../vendor/contrib/analysis-extras/lib/*:${SOLR_HOME}/../vendor/server/solr-webapp/webapp/WEB-INF/lib/*"

mkdir -p ${TMP_RAMDISK_DIR}
if ! mountpoint --quiet ${TMP_RAMDISK_DIR}; then
   mount -t tmpfs -o rw,size=10G tmpfs ${TMP_RAMDISK_DIR}
fi

# make index work with replicated index
# current index is stored in the last line of index.properties
function locate_index
{
    local targetVar=$1
    local indexDir=$2
    # default value
    local subDir="index"

    if [ -e $indexDir/index.properties ]
    then
        # read it into an array
        readarray farr < $indexDir/index.properties
        # get the last line
        indexline="${farr[${#farr[@]}-1]}"
        # parse the lastline to just get the filename
        subDir=`echo $indexline | sed s/index=//`
    fi

    eval $targetVar="$indexDir/$subDir"
}

locate_index "bib_index" "${SOLR_HOME}/biblio"
locate_index "auth_index" "${SOLR_HOME}/authority"
index_dir="${SOLR_HOME}/alphabetical_browse"

mkdir -p "$index_dir"

function build_browse
{
    browse=$1
    field=$2
    skip_authority=$3

    extra_jvm_opts=$4
    filter=$5

    [[ ! -z $filter ]] && browse_unique=${TMP_RAMDISK_DIR}/${browse}-${filter} || browse_unique=${TMP_RAMDISK_DIR}/${browse}

    if [ "$skip_authority" = "1" ]; then
        $JAVA ${extra_jvm_opts} -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp $CLASSPATH PrintBrowseHeadings "$bib_index" "$field" "" "${browse_unique}.tmp" "$filter"
    else
        $JAVA ${extra_jvm_opts} -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp $CLASSPATH PrintBrowseHeadings "$bib_index" "$field" "$auth_index" "${browse_unique}.tmp" "$filter"
    fi

    if [[ ! -z $filter ]]; then
        out_dir="$index_dir/$filter"
        mkdir -p "$out_dir"
        chown solr:solr $out_dir
    else
        out_dir="$index_dir"
    fi

    sort -T ${TMP_RAMDISK_DIR} -u -t$'\1' -k1 "${browse_unique}.tmp" -o "${browse_unique}_sorted.tmp"
    $JAVA -Dfile.encoding="UTF-8" -cp $CLASSPATH CreateBrowseSQLite "${browse_unique}_sorted.tmp" "${browse_unique}_browse.db"


    mv "${browse_unique}_browse.db" "$out_dir/${browse}_browse.db-updated"
    touch "$out_dir/${browse}_browse.db-ready"
    chown -R solr:solr "$out_dir"
}


function GenerateIndexForSystem {
    system_flag="$1"
    echo build_browse "hierarchy" "hierarchy_browse" 1 "" ${system_flag}
    time build_browse "hierarchy" "hierarchy_browse" 1 "" ${system_flag}
    echo build_browse "title" "title_fullStr" 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_fullStr -Dvaluefield=title_fullStr" ${system_flag}
    time build_browse "title" "title_fullStr" 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_fullStr -Dvaluefield=title_fullStr" ${system_flag}
    echo build_browse "topic" "topic_browse" 1 "" ${system_flag}
    time build_browse "topic" "topic_browse" 1 "" ${system_flag}
    echo build_browse "author" "author_browse" "" 1 ${system_flag}
    time build_browse "author" "author_browse" 1 "" ${system_flag}
    echo build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer" ${system_flag}
    time build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer" ${system_flag}
    echo build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer" ${system_flag}
    time build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer" ${system_flag}
}


GenerateIndexForSystem &
GenerateIndexForSystem "is_religious_studies" &
rm -f ${TMP_RAMDISK_DIR}/*.tmp
wait
GenerateIndexForSystem "is_biblical_studies" &
GenerateIndexForSystem "is_canon_law" &
wait
echo "Finished generating alphabrowse indices..."
