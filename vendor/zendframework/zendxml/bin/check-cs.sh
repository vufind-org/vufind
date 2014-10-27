#!/bin/bash

FIXER_CONFIG="`dirname $0`/../.php_cs"
FIXER_PATH="`dirname $0`/../vendor/fabpot/php-cs-fixer/php-cs-fixer"
CS=""

(
    cd "`dirname $0`/../" ;
    CS=$(php vendor/fabpot/php-cs-fixer/php-cs-fixer fix -v --dry-run .)
)

if [[ "$CS" ]];
then
    echo -en '\E[31m'"$CS\033[1m\033[0m";
    printf "\n";
    echo -en '\E[31;47m'"\033[1mCoding standards check failed!\033[0m"
    printf "\n";
    exit 2;
fi

echo -en '\E[32m'"\033[1mCoding standards check passed!\033[0m"
printf "\n";

echo $CS
