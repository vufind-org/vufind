@echo off
:: filterrecords.bat
:: Grep for marc records
setlocal
::Get the current batch file's short path
for %%x in (%~f0) do set scriptdir=%%~dpsx
for %%x in (%scriptdir%) do set scriptdir=%%~dpsx

if EXIST %scriptdir%SolrMarc.jar goto doit
pushd %scriptdir%..
for %%x in (%CD%) do set scriptdir=%%~sx\
popd

:doit

java -Dsolrmarc.main.class="org.solrmarc.marc.MarcPrinter" -Dmarc.delete_subfields="%1" -Dmarc.combine_records="" -jar %scriptdir%SolrMarc.jar translate %2 %3 
goto done

:done
