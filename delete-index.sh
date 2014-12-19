#!/bin/bash

# Stop VuFind
./vufind.sh stop

# Delete Indexes
echo "Are you sure you want to delete the index? [y/n]"
read yesno
if [[ "$yesno" == "y" ]];then
    echo "rm -rv solr/biblio/data/index"
    rm -rv solr/biblio/data/index
    echo "rm -rv solr/biblio/data/spellchecker"
    rm -rv solr/biblio/data/spellchecker
    echo "rm -rv solr/biblio/data/spellShingle"
    rm -rv solr/biblio/data/spellShingle
fi

# Start VuFind
./vufind.sh start
