@echo off
rem Startup script for the VuFind Jetty Server under Windows
rem
rem Configuration variables
rem
rem VUFIND_HOME
rem   Home of the VuFind installation.
rem
rem SOLR_BIN
rem   Home of the Solr executable scripts.
rem
rem SOLR_HEAP
rem   Size of the Solr heap (i.e. 512M, 2G, etc.). Defaults to 1G.
rem
rem SOLR_HOME
rem   Home of the Solr indexes and configurations.
rem
rem SOLR_PORT
rem   Network port for Solr. Defaults to 8080.
rem
rem JAVA_HOME
rem   Home of Java installation (not directly used by this script, but passed along to
rem   the standard Solr control script).
rem
rem SOLR_ADDITIONAL_START_OPTIONS
rem   Additional options to pass to the solr binary at startup.
rem
rem SOLR_ADDITIONAL_JVM_OPTIONS
rem   Additional options to pass to the JVM when launching Solr.

rem Make sure that environment edits are local and that we have access to the
rem Windows command extensions.
setlocal enableextensions
if not errorlevel 1 goto extensionsokay
echo Unable to enable Windows command extensions.
goto end
:extensionsokay

rem Unrecognized action -- display help text
if "!%1!"=="!!" goto usage

rem Set VUFIND_HOME (if not already set)
if not (!%VUFIND_HOME%!)==(!!) goto vufindhomefound
rem VUFIND_HOME not set -- try to call env.bat to 
rem fix the problem before we give up completely
if exist env.bat goto useenvbat
rem If env.bat doesn't exist, the user hasn't run the installer yet.
echo WARNING: env.bat does not exist -- trying default environment settings.
echo Please run "php install.php" to correct this problem.
rem Extract path from current batch file and trim trailing slash:
set VUFIND_HOME=%~dp0%
set VUFIND_HOME=%VUFIND_HOME:~0,-1%
goto vufindhomefound
:useenvbat
call env > nul
if not (!%VUFIND_HOME%!)==(!!) goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound

rem Set SOLR_HOME
if not "!%SOLR_HOME%!"=="!!" goto solrhomeset
set SOLR_HOME=%VUFIND_HOME%\solr\vufind
:solrhomeset

rem Set SOLR_LOGS_DIR
if not "!%SOLR_LOGS_DIR%!"=="!!" goto solrlogsdirset
set SOLR_LOGS_DIR=%SOLR_HOME%\logs
:solrlogsdirset

rem Set SOLR_BIN
if not "!%SOLR_BIN%!"=="!!" goto solrbinset
set SOLR_BIN=%VUFIND_HOME%\solr\vendor\bin
:solrbinset

rem Set SOLR_HEAP
if not "!%SOLR_HEAP%!"=="!!" goto solrheapset
set SOLR_HEAP=1G
:solrheapset

rem Set SOLR_PORT
if not "!%SOLR_PORT%!"=="!!" goto solrportset
set SOLR_PORT=8080
:solrportset

call %SOLR_BIN%\solr.cmd %1 %SOLR_ADDITIONAL_START_OPTIONS% -p %SOLR_PORT% -s %SOLR_HOME% -m %SOLR_HEAP% -a "-Ddisable.configEdit=true -Dsolr.log=%SOLR_LOGS_DIR% %SOLR_ADDITIONAL_JVM_OPTIONS%"
goto end

:usage
echo Usage: solr {start/stop/restart/status}
goto end

:end
rem We're all done -- close down the local environment.
endlocal
