@echo off
:: getrecord.bat
:: Program to extract one or more MARC records from a file 
setlocal
::Get the current batch file's short path
for %%x in (%~f0) do set scriptdir=%%~dpsx
for %%x in (%scriptdir%) do set scriptdir=%%~dpsx

if EXIST %scriptdir%SolrMarc.jar goto doit
pushd %scriptdir%..
for %%x in (%CD%) do set scriptdir=%%~sx\
popd

:doit
set inarg=%1

set arg1=-
for /f "delims=" %%a in ('echo %inarg% ^| findstr "\.mrc"') do @set arg1=%%a

if "%arg1%" EQU "-" set arg2=%1
if "%arg1%" NEQ "-" set arg2=%2

if "%arg1%" EQU "-" set arg3=%2 
if "%arg1%" NEQ "-" set arg3=%3

if "%arg1%" EQU "-" set arg4=%3 
if "%arg1%" NEQ "-" set arg4=%4

java -Dsolrmarc.main.class="org.solrmarc.marc.RawRecordReader" -jar %scriptdir%SolrMarc.jar %arg1% %arg2% %arg3% %arg4%
