@echo off
:: filterrecords.bat
:: Grep for marc records
:: $Id: filterrecords.bat
setlocal
::Get the current batch file's short path
for %%x in (%~f0) do set scriptdir=%%~dpsx
for %%x in (%scriptdir%) do set scriptdir=%%~dpsx

if EXIST %scriptdir%SolrMarc.jar goto doit
pushd %scriptdir%..
for %%x in (%CD%) do set scriptdir=%%~sx\
popd

:doit

set arg=%1
if "%arg:~0,1%" == "-" goto missing
java -Dsolrmarc.main.class="org.solrmarc.marc.MarcPrinter" -Dmarc.include_if_present="%1" -Dmarc.combine_records="" -jar %scriptdir%SolrMarc.jar translate %2 %3 
goto done
:missing
set arg1=%arg:~1%
java -Dsolrmarc.main.class="org.solrmarc.marc.MarcPrinter" -Dmarc.include_if_missing="%arg1%" -Dmarc.combine_records="" -jar %scriptdir%SolrMarc.jar translate %2 %3 

:done
