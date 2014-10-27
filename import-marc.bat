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
if not "!%SOLRCORE%!"=="!!" goto solrcorefound
set SOLRCORE=biblio
:solrcorefound

rem ##################################################
rem # Set SOLR_HOME
rem ##################################################
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
rem VUFIND_HOME not set -- try to call vufind.bat to 
rem fix the problem before we give up completely
if exist vufind.bat goto usevufindbat
rem If vufind.bat doesn't exist, the user hasn't run the installer yet.
echo ERROR: vufind.bat does not exist -- could not set up environment.
echo Please run "php install.php" to correct this problem.
goto end
:usevufindbat
call vufind > nul
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound
if "!%SOLR_HOME%!"=="!!" goto solrhomenotfound
set EXTRA_SOLRMARC_SETTINGS=%EXTRA_SOLRMARC_SETTINGS% -Dsolr.path=%SOLR_HOME% -Dsolr.solr.home=%SOLR_HOME% -Dsolrmarc.solr.war.path=%SOLR_HOME%/jetty/webapps/solr.war
:solrhomenotfound

rem ##################################################
rem # Set SOLRMARC_HOME
rem ##################################################
if "!%SOLRMARC_HOME%!"=="!!" goto solrmarchomenotfound
set EXTRA_SOLRMARC_SETTINGS=%EXTRA_SOLRMARC_SETTINGS% -Dsolrmarc.path=%SOLRMARC_HOME%
:solrmarchomenotfound

rem #####################################################
rem # Build java command
rem #####################################################
if not "!%JAVA_HOME%!"=="!!" goto javahomefound
set JAVA=java
goto javaset
:javahomefound
set JAVA="%JAVA_HOME%\bin\java"
:javaset

rem ##################################################
rem # Set properties file if not already provided
rem ##################################################
if not "!%PROPERTIES_FILE%!"=="!!" goto propertiesfound
if not exist %VUFIND_LOCAL_DIR%\import\import.properties goto nolocalproperties
set PROPERTIES_FILE=%VUFIND_LOCAL_DIR%\import\import.properties
goto propertiesfound
:nolocalproperties
set PROPERTIES_FILE=%VUFIND_HOME%\import\import.properties
:propertiesfound

rem ##################################################
rem # Set Command Options
rem ##################################################
set JAR_FILE=%VUFIND_HOME%\import\SolrMarc.jar

rem #####################################################
rem # Execute Importer
rem #####################################################
set RUN_CMD=%JAVA% %INDEX_OPTIONS% -Duser.timezone=UTC -Dsolr.core.name=%SOLRCORE% %EXTRA_SOLRMARC_SETTINGS% -jar %JAR_FILE% %PROPERTIES_FILE% %1
echo Now Importing %1 ...
echo %RUN_CMD%
%RUN_CMD%

:end

rem We're all done -- close down the local environment.
endlocal