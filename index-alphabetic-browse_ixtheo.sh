#!/bin/bash
set -o errexit

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

    if [ "$skip_authority" = "1" ]; then
        $JAVA ${extra_jvm_opts} -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp $CLASSPATH PrintBrowseHeadings "$bib_index" "$field" "" "${browse}.tmp" "$filter"
    else
        $JAVA ${extra_jvm_opts} -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp $CLASSPATH PrintBrowseHeadings "$bib_index" "$field" "$auth_index" "${browse}.tmp" "$filter"
    fi

    if [[ ! -z $filter ]]; then
        out_dir="$index_dir/$filter"
        mkdir -p "$out_dir"
        chown solr:solr $out_dir
    else
        out_dir="$index_dir"
    fi

    sort -T /var/tmp -u -t$'\1' -k1 "${browse}.tmp" -o "sorted-${browse}.tmp"
    $JAVA -Dfile.encoding="UTF-8" -cp $CLASSPATH CreateBrowseSQLite "sorted-${browse}.tmp" "${browse}_browse.db"

    rm -f *.tmp

    mv "${browse}_browse.db" "$out_dir/${browse}_browse.db-updated"
    touch "$out_dir/${browse}_browse.db-ready"
    chown -R solr:solr "$out_dir"
}

build_browse "hierarchy" "hierarchy_browse" 1
build_browse "title" "title_fullStr" 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_fullStr -Dvaluefield=title_fullStr"
build_browse "topic" "topic_browse" 1
build_browse "author" "author_browse" 1
build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer"
build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer"

build_browse "hierarchy" "hierarchy_browse" 1 "" "is_religious_studies"
build_browse "title" "title_fullStr" 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_fullStr -Dvaluefield=title_fullStr" "is_religious_studies"
build_browse "topic" "topic_browse" 1 "" "is_religious_studies"
build_browse "author" "author_browse" 1 "" "is_religious_studies"
build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer" "is_religious_studies"
build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer" "is_religious_studies"

build_browse "hierarchy" "hierarchy_browse" 1 "" "is_biblical_studies"
build_browse "title" "title_fullStr" 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_fullStr -Dvaluefield=title_fullStr" "is_biblical_studies"
build_browse "topic" "topic_browse" 1 "" "is_biblical_studies"
build_browse "author" "author_browse" 1 "" "is_biblical_studies"
build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer" "is_biblical_studies"
build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer" "is_biblical_studies"

build_browse "hierarchy" "hierarchy_browse" 1 "" "is_canon_law"
build_browse "title" "title_fullStr" 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_fullStr -Dvaluefield=title_fullStr" "is_canon_law"
build_browse "topic" "topic_browse" 1 "" "is_canon_law"
build_browse "author" "author_browse" 1 "" "is_canon_law"
build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer" "is_canon_law"
build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer" "is_canon_law"
