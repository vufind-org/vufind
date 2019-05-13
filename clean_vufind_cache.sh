#!/bin/sh
VUFIND_LOCAL_DIR="/usr/local/vufind/local/tuefind/instances"

for i in "ixtheo" "relbib" "bibstudies" "krimdok" "churchlaw"
do
    echo "Removing ${VUFIND_LOCAL_DIR}/${i}/cache/*"
    rm -rf ${VUFIND_LOCAL_DIR}/${i}/cache/*
done
