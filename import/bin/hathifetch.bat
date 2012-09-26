@echo off
:: hathifetch.bat
:: Program to retrieve JSON records from Hathi Trust and extract the 
:: marc records from those JSON records.
:: $Id: hathifetch.bat 
setlocal
::Get the current batch file's short path
for %%x in (%~f0) do set scriptdir=%%~dpsx
for %%x in (%scriptdir%) do set scriptdir=%%~dpsx

if EXIST %scriptdir%SolrMarc.jar goto checkargs
pushd %scriptdir%..
for %%x in (%CD%) do set scriptdir=%%~sx\
popd

:checkargs
if "%1"=="" GOTO doit
    echo "    Usage: hathifetch.bat [-s NumToSkip] [-n NumToReturn] file_with_ids "
    echo "      or : hathifetch.bat [-s NumToSkip] [-n NumToReturn] url_with_ids "
    echo "      or : cat file_with_hathi_ids | hathifetch.bat [-s NumToSkip] [-n NumToReturn]"
    echo "      other options  -d = debug     retrieve and print the recordURL strings only"
    echo "                     -v = verbose   fetch records and print them out as Ascii Marc"
    echo "                     -856 = add856   add 856 fields to the records based on the 974 fields"
    echo "      Note: file_with_ids can be Hathi Record numbers only (one per line), or Hathi Data listings"
    echo "            like the following line where the Hathi Record number is one of several entries on a line."
    echo "      Note also that the program supports reading gzipped input files."
GOTO done

:doit

java -Dsolrmarc.main.class="org.solrmarc.tools.HathiPlunderer" -jar %scriptdir%SolrMarc.jar %1 %2 %3 %4 %5 %6 %7 %8 %9

:done
