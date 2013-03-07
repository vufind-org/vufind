@echo off
rem Make sure that environment edits are local and that we have access to the 
rem Windows command extensions.
setlocal enableextensions
if not errorlevel 1 goto extensionsokay
echo Unable to enable Windows command extensions.
goto end
:extensionsokay

rem Make sure VUFIND_HOME is set:
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
rem VUFIND_HOME not set -- try to call vufind.bat to 
rem fix the problem before we give up completely
if exist %0\..\..\vufind.bat goto usevufindbat
rem If vufind.bat doesn't exist, the user hasn't run install.bat yet.
echo ERROR: vufind.bat does not exist -- could not set up environment.
echo Please run install.bat to correct this problem.
goto end
:usevufindbat
cd %0\..\..
call vufind > nul
cd %0\..
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound

rem Make sure command line parameter was included:
if not "!%1!"=="!!" goto paramsokay
echo This script deletes records based on files created by the OAI-PMH harvester.
echo.
echo Usage: %0 [harvest subdirectory] [index type]
echo.
echo [harvest subdirectory] is a directory name created by the OAI-PMH harvester.
echo This script will search the harvest subdirectories of the directories defined
echo by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables.
echo.
echo [index type] is optional; defaults to Solr for main bibliographic index, but
echo can be set to SolrAuth for authority index.
echo.
echo Example: %0 oai_source
goto end
:paramsokay

rem Check if the path is valid:
set BASEPATH="%VUFIND_LOCAL_DIR%\harvest\%1"
if exist %BASEPATH% goto basepathfound
set BASEPATH="%VUFIND_HOME%\harvest\%1"
if exist %BASEPATH% goto basepathfound
echo Directory %BASEPATH% does not exist!
goto end
:basepathfound

rem Create log/processed directories as needed:
if exist %BASEPATH%\processed goto processedfound
md %BASEPATH%\processed
:processedfound

rem Process all the files in the target directory:
cd %VUFIND_HOME%\util
set FOUNDSOME=0
for %%a in (%BASEPATH%\*.delete) do (
  set FOUNDSOME=1
  echo Processing %%a...
  php deletes.php %%a flat %2
  move %%a %BASEPATH%\processed\ > nul
)

if "%FOUNDSOME%"=="0" goto end

echo Optimizing index...
php optimize.php

:end