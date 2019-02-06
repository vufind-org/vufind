@echo off
rem Batch file to start the import of a binary marc file for Solr indexing.
rem
rem VUFIND_HOME
rem     Path to the vufind installation
rem SOLRMARC_HOME
rem     Path to the solrmarc installation
rem JAVA_HOME
rem     Path to the java
rem INDEX_OPTIONS
rem     Options to pass to the JVM

rem Make sure that environment edits are local and that we have access to the 
rem Windows command extensions.
setlocal enableextensions
if not errorlevel 1 goto extensionsokay
echo Unable to enable Windows command extensions.
goto end
:extensionsokay

rem Save %0 for later in case the batch file's name gets shifted away
set THISFILE=%0

rem #####################################################
rem # handle the -p option to override properties file
rem #####################################################
if "%1"=="-p" (
  set PROPERTIES_FILE=%2
  shift
  shift
)

rem #####################################################
rem # Make sure we have the expected number of arguments
rem #####################################################
if not "!%1!"=="!!" goto argfound
echo     Usage: %THISFILE% [-p c:\path\to\import.properties] c:\path\to\marc.mrc
goto end
:argfound

rem ##################################################
rem # Set INDEX_OPTIONS
rem #   Tweak these in accordance to your needs
rem # Xmx and Xms set the heap size for the Java Virtual Machine
rem # You may also want to add the following:
rem # -XX:+UseParallelGC
rem # -XX:+AggressiveOpts
rem ##################################################
if not "!%INDEX_OPTIONS%!"=="!!" goto indexoptionsfound
set INDEX_OPTIONS=-Xms512m -Xmx512m -DentityExpansionLimit=0
:indexoptionsfound

rem ##################################################
rem # Set SOLRCORE
rem ##################################################
if "!%SOLRCORE%!"=="!!" goto solrcorenotfound
set EXTRA_SOLRMARC_SETTINGS=%EXTRA_SOLRMARC_SETTINGS%  -Dsolr.core.name=%SOLRCORE%
:solrcorenotfound

rem ##################################################
rem # Set VUFIND_HOME
rem ##################################################
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

rem #####################################################
rem # Build java command
rem #####################################################
if not (!%JAVA_HOME%!)==(!!) goto javahomefound
set JAVA=java
goto javaset
:javahomefound
set JAVA=%JAVA_HOME%\bin\java
:javaset

rem ##################################################
rem # Set properties file if not already provided
rem ##################################################
if not "!%PROPERTIES_FILE%!"=="!!" goto propertiesfound
if not exist %VUFIND_LOCAL_DIR%\import\import.properties goto nolocalproperties
set PROPERTIES_FILE=%VUFIND_LOCAL_DIR%\import\import.properties
goto propertiesfound
:nolocalproperties
echo WARNING: VUFIND_LOCAL_DIR environment variable is not set. Is this intentional?
set PROPERTIES_FILE=%VUFIND_HOME%\import\import.properties
:propertiesfound

rem ##################################################
rem # Set Command Options
rem ##################################################
for %%a in (%VUFIND_HOME%\import\solrmarc_core_*.jar) do set JAR_FILE=%%a

rem #####################################################
rem # Execute Importer
rem #####################################################
set RUN_CMD=%JAVA% %INDEX_OPTIONS% -Duser.timezone=UTC %EXTRA_SOLRMARC_SETTINGS% -jar %JAR_FILE% %PROPERTIES_FILE% -solrj %VUFIND_HOME%\solr\vendor\dist\solrj-lib %1
echo Now Importing %1 ...
echo %RUN_CMD%
%RUN_CMD%

:end

rem We're all done -- close down the local environment.
endlocal
