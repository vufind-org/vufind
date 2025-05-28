#!/bin/sh

set -eu

cd "$FINNA_DOCUMENT_ROOT"

parallel curl --create-dirs -O --output-dir \
    "${FINNA_DOCUMENT_ROOT}/local/languages/finna/" \
    https://www.finna-pre.fi/{}-datasources.ini ::: fi sv en-gb se

composer install
composer install-build-deps
npm install
npm run finna:build:scss

cd -

exec apache2-foreground
