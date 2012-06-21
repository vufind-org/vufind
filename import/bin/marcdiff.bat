@echo off
:: marcdiff.sh
:: Diagnostic program to show look for differences between Marc record files.
:: $Id: marcdiff.sh 
setlocal
::Get the current batch file's short path
for %%x in (%~f0) do set scriptdir=%%~dpsx
for %%x in (%scriptdir%) do set scriptdir=%%~dpsx

if EXIST %scriptdir%SolrMarc.jar goto doit
pushd %scriptdir%..
for %%x in (%CD%) do set scriptdir=%%~sx\
popd

:doit

java -Dsolrmarc.main.class="org.solrmarc.tools.MarcDiff" -jar %scriptdir%SolrMarc.jar %1 %2 %3 
