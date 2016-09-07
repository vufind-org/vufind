@echo off
:: marcerror.sh
:: Diagnostic program to show look for errors in Marc records.
setlocal
::Get the current batch file's short path
for %%x in (%~f0) do set scriptdir=%%~dpsx
for %%x in (%scriptdir%) do set scriptdir=%%~dpsx

if EXIST %scriptdir%SolrMarc.jar goto doit
pushd %scriptdir%..
for %%x in (%CD%) do set scriptdir=%%~sx\
popd

:doit
::echo BatchPath = %scriptdir%

java -Dsolrmarc.main.class="org.solrmarc.tools.PermissiveReaderTest" -jar %scriptdir%SolrMarc.jar %1 %2 %3
