#!/bin/sh

########################################################################################
# join_marcxml_files.sh
########################################################################################
#
# This script reads MARCXML files (ending in ".xml") in a given directory, and combines
# them into a collection of MARCXML files, enclosing the collection with <marc:collection>.
# 
# This is useful because the batch-import* scripts in VuFind harvest each MARCXML record
# into its own separate file, resulting in a huge number of files. Indexing such a large
# number of MARCXML files into SOLR is very slow.
#
# E.g., if your harvest directory is named "myharvest" and contains 100000 individal
# MARCXML records (one record per file), then running this script will combine those
# records into just two MARCXML files, each containing 50000 records (if the variable
# MAXCNT is set to its default value of 50000). The "myharvest" directory will ultimately
# look like the following:
#
# myharvest_combined_0000000000.xml  myharvest_combined_0000000001.xml
#
# and the original files will be preserved in directory called "backup_myharvest."
#
########################################################################################


########################################################################################
#
# MAXCNT is the number of original files that will be combined into a single file.
#        It must be <= 64000, unless you run the JVM with the following option:
#
#    -DentityExpansionLimit=0
#
MAXCNT=50000
########################################################################################

if [ $# != 1 ]; then
   echo "Usage: $0 [directory]"
   exit 1
fi
DIR=$1
DIR=`echo ${DIR} | sed "s,/$,,"`

if [ ! -d "${DIR}" ]; then
   echo "Directory ${DIR} doesn't exist!"
   exit 1
fi

cd $DIR
CNT=0
TOTAL_CNT=0
FILE_CNT=0
for i in `ls`; do
   IS_XML=`echo ${i} | egrep "\.[Xx][Mm][Ll]$" | wc -l`
   if [ $IS_XML -eq 1 ]; then
      if [ $CNT -ge $MAXCNT ]; then
         filename="${DIR}_combined_`printf "%010d" ${FILE_CNT}`.xml"
         echo "echo \"</marc:collection>\" >> \"${filename}\""
         echo "</marc:collection>" >> "${filename}"
         FILE_CNT=`expr $FILE_CNT + 1`;
         CNT=0
      fi

      filename="${DIR}_combined_`printf "%010d" ${FILE_CNT}`.xml"

      if [ $CNT -eq 0 ]; then
         if [ $FILE_CNT -eq 0 ]; then
            echo "mkdir -p \"../backup_${DIR}\""
            mkdir -p "../backup_${DIR}"
         fi
         echo "echo \"<marc:collection xmlns:marc=\"http://www.loc.gov/MARC21/slim\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd\">\" >> \"${filename}\""
         echo "<marc:collection xmlns:marc=\"http://www.loc.gov/MARC21/slim\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd\">" >> "${filename}"
      fi

      echo "cat \"${i}\" >> \"${filename}\""
      cat "${i}" >> "${filename}"

      echo "mv \"${i}\" \"../backup_${DIR}\""
      mv "${i}" "../backup_${DIR}"

      CNT=`expr $CNT + 1`;
      TOTAL_CNT=`expr $TOTAL_CNT + 1`;

      echo "."
   fi
done

echo "echo \"</marc:collection>\" >> \"${filename}\""
echo "</marc:collection>" >> "${filename}"

echo count: $TOTAL_CNT
