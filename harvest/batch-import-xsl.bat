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
rem VUFIND_HOME not set -- try to call env.bat to 
rem fix the problem before we give up completely
if exist %0\..\..\env.bat goto useenvbat
rem If env.bat doesn't exist, the user hasn't run the installer yet.
echo ERROR: env.bat does not exist -- could not set up environment.
echo Please run install.php to correct this problem.
goto end
:useenvbat
cd %0\..\..
call env > nul
cd %0\..
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound

rem Save script name for message below (otherwise it may get shifted away)
set SCRIPT_NAME=%0

rem Set default behavior
set SKIP_OPTIMIZE=0

rem Process switches
:switchloop
if "%1"=="-s" goto sswitch
goto switchloopend
:sswitch
set SKIP_OPTIMIZE=1
shift
goto switchloop
:switchloopend

rem Make sure command line parameter was included:
if not "!%2!"=="!!" goto paramsokay
echo This script processes a batch of harvested XML records using the specified XSL
echo import configuration file.
echo.
echo Usage: %SCRIPT_NAME% [harvest subdirectory] [properties file]
echo.
echo [harvest subdirectory] is a directory name created by the OAI-PMH harvester.
echo This script will search the harvest subdirectories of the directories defined
echo by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables.
echo.
echo [properties file] is a configuration file found in the import subdirectory of
echo either your VUFIND_LOCAL_DIR or VUFIND_HOME directory.
echo.
echo Example: %SCRIPT_NAME% oai_source ojs.properties
echo.
echo Options:
echo -s:  Skip optimize operation after importing.
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

rem Flag -- do we need to perform an optimize?
set OPTIMIZE=0

rem Process all the files in the target directory:
cd %VUFIND_HOME%\import
for %%a in (%BASEPATH%\*.xml) do (
  echo Processing %%a...
  php import-xsl.php %%a %2
  rem Unfortunately, PHP doesn't seem to set appropriate errorlevels, so error
  rem detection doesn't work under Windows like it does under Linux... however,
  rem this code is retained in case PHP's behavior improves in the future!
  if errorlevel 0 (
    move %%a %BASEPATH%\processed\ > nul
    rem We processed a file, so we need to optimize later on:
    set OPTIMIZE=1
  )
)

rem Optimize the index now that we are done (if necessary):
if not "%OPTIMIZE%!"=="1!" goto end
if not "%SKIP_OPTIMIZE%!"=="0!" goto end
cd %VUFIND_HOME%\util
echo Optimizing index...
php optimize.php

:end