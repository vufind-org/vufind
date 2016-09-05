@echo off
rem Wrapper around import-marc.sh to allow import of authority records.

rem No arguments?  Display syntax:
if not "!%1!"=="!!" goto argfound
echo     Usage: %0 c:\path\to\marc.mrc [properties file]
goto end
:argfound

rem Make sure we know where the VuFind home directory lives:
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
rem VUFIND_HOME not set -- try to call env.bat to 
rem fix the problem before we give up completely
if exist env.bat goto useenvbat
rem If env.bat doesn't exist, the user hasn't run the installer yet.
echo ERROR: env.bat does not exist -- could not set up environment.
echo Please run "php install.php" to correct this problem.
goto end
:useenvbat
call env > nul
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound

rem Always use the standard authority mappings; if the user specified an override
rem file, add that to the setting.
if not exist %VUFIND_LOCAL_DIR%\import\marc_auth.properties goto nolocalmappings
set MAPPINGS_FILE=%VUFIND_LOCAL_DIR%\import\marc_auth.properties
goto mappingsset
:nolocalmappings
set MAPPINGS_FILE=%VUFIND_HOME%\import\marc_auth.properties
:mappingsset
if "!%2!"=="!!" goto noextramappings
if not exist %VUFIND_LOCAL_DIR%\import\%2 goto nolocalextramappings
set MAPPINGS_FILE=%MAPPINGS_FILE%,%VUFIND_LOCAL_DIR%\import\%2
goto noextramappings
:nolocalextramappings
set MAPPINGS_FILE=%MAPPINGS_FILE%,%VUFIND_HOME%\import\%2
:noextramappings

rem Override some settings in the standard import script:
if not exist %VUFIND_LOCAL_DIR%\import\import_auth.properties goto nolocalproperties
set PROPERTIES_FILE=%VUFIND_LOCAL_DIR%\import\import_auth.properties
goto propertiesfound
:nolocalproperties
set PROPERTIES_FILE=%VUFIND_HOME%\import\import_auth.properties
:propertiesfound

set SOLRCORE="authority"
set EXTRA_SOLRMARC_SETTINGS="-Dsolr.indexer.properties=%MAPPINGS_FILE%"

rem Call the standard script:
call %VUFIND_HOME%\import-marc.bat %1

:end