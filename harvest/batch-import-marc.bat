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

rem Find harvest directory for future use:
set HARVEST_DIR=%VUFIND_LOCAL_DIR%\harvest
if exist %HARVEST_DIR% goto harvestpathfound
set HARVEST_DIR=%VUFIND_HOME%\harvest
:harvestpathfound

set BASEPATH_UNDER_HARVEST=1
set LOGGING=1
set MOVE_DATA=1

rem Save script name for message below (otherwise it may get shifted away)
set SCRIPT_NAME=%0

rem Process switches
:switchloop
if "%1"=="-d" goto dswitch
if "%1"=="-h" goto helpmessage
if "%1"=="-m" goto mswitch
if "%1"=="-p" goto pswitch
if "%1"=="-x" goto xswitch
if "%1"=="-z" goto zswitch
goto switchloopend
:dswitch
set BASEPATH_UNDER_HARVEST=0
shift
goto switchloop
:mswitch
set MOVE_DATA=0
shift
goto switchloop
:pswitch
set PROPERTIES_FILE=%2
shift
shift
goto switchloop
:xswitch
echo The -x switch is not currently supported under Windows.
echo See https://vufind.org/jira/browse/VUFIND-1626 for more details.
goto end
:zswitch
set LOGGING=0
shift
goto switchloop
:switchloopend

rem Make sure command line parameter was included:
if not "!%1!"=="!!" goto paramsokay
:helpmessage
echo This script processes a batch of harvested MARC records.
echo.
echo Usage: %SCRIPT_NAME% [-dhmz] [-p properties_file] [harvest subdirectory]
echo.
echo [harvest subdirectory] is a directory name created by the OAI-PMH harvester.
echo This script will search the harvest subdirectories of the directories defined
echo by the VUFIND_LOCAL_DIR and VUFIND_HOME environment variables.
echo.
echo Example: %0 oai_source
echo.
echo Options:
echo -d:  Use the directory path as-is, do not append it to %HARVEST_DIR%.
echo      Useful for non-OAI batch loading.
echo -h:  Print this message
echo -m:  Do not move the data files after importing.
echo -p:  Used specified SolrMarc configuration properties file
echo -z:  No logging.
goto end
:paramsokay

rem Check if the path is valid:
set BASEPATH="%HARVEST_DIR%\%1"
if "%BASEPATH_UNDER_HARVEST%"=="1" goto checkbasepath
set BASEPATH="%1"
:checkbasepath
if exist %BASEPATH% goto basepathfound
echo Directory %BASEPATH% does not exist!
goto end
:basepathfound

rem Create log/processed directories as needed:
if exist %BASEPATH%\log goto logfound
if "%LOGGING%"=="0" goto logfound
md %BASEPATH%\log
:logfound
if exist %BASEPATH%\processed goto processedfound
if "%MOVE_DATA%"=="0" goto processedfound
md %BASEPATH%\processed
:processedfound

rem Process all the files in the target directory:
for %%a in (%BASEPATH%\*.xml %BASEPATH%\*.mrc %BASEPATH%\*.marc) do (
  call :run_command %%a %BASEPATH%\log\%%~nxa.log
  if not errorlevel 1 (
    if "%MOVE_DATA%"=="1" (
      move %%a %BASEPATH%\processed\ > nul
    )
  )
)
goto :end

rem Subroutine to do the SolrMarc ingest
:run_command
if "%LOGGING%"=="0" (
  call %VUFIND_HOME%\import-marc.bat %1
  if not errorlevel 1 exit /b 0
)
rem Capture solrmarc output to log
if "%LOGGING%"=="1" (
  call %VUFIND_HOME%\import-marc.bat %1 2> %2
  if not errorlevel 1 exit /b 0
)
exit /b 1

:end
