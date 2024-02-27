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
echo WARNING: env.bat does not exist -- trying default environment settings.
echo Please run "php install.php" to correct this problem.
rem Extract path from current batch file and trim trailing slash:
set VUFIND_HOME=%~dp0%
set VUFIND_HOME=%VUFIND_HOME:~0,-1%
goto vufindhomefound
:useenvbat
call env > nul
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound

rem Override some settings in the standard import script:
if not exist %VUFIND_LOCAL_DIR%\import\import_auth.properties goto nolocalproperties
set PROPERTIES_FILE=%VUFIND_LOCAL_DIR%\import\import_auth.properties
goto propertiesfound
:nolocalproperties
echo WARNING: VUFIND_LOCAL_DIR environment variable is not set. Is this intentional?
set PROPERTIES_FILE=%VUFIND_HOME%\import\import_auth.properties
:propertiesfound

rem Always use the authority mappings from PROPERTIES_FILE
rem if the user specified an override file, add that to the setting.
set MAPPINGS_FILENAMES=""
for /f "delims=" %%a in ('findstr "^solr.indexer.properties" %PROPERTIES_FILE%') do set MAPPINGS_FILENAMES=%%a
set MAPPINGS_FILENAMES="%MAPPINGS_FILENAMES:solr.indexer.properties=%"
if not "%2"=="" set MAPPINGS_FILENAMES=%MAPPINGS_FILENAMES%,%2
set MAPPINGS_FILENAMES=%MAPPINGS_FILENAMES:"=%

setlocal EnableDelayedExpansion
set MAPPINGS_FILES=""
for %%a in (%MAPPINGS_FILENAMES%) do (
    if not !MAPPINGS_FILES!=="" set MAPPINGS_FILES=!MAPPINGS_FILES!,
    if exist %VUFIND_LOCAL_DIR%\import\%%a (
        set MAPPINGS_FILES=!MAPPINGS_FILES!%VUFIND_LOCAL_DIR%\import\%%a
    ) else (
        set MAPPINGS_FILES=!MAPPINGS_FILES!%VUFIND_HOME%\import\%%a
    )
)
set MAPPINGS_FILES=%MAPPINGS_FILES:~2,99999%
setlocal DisableDelayedExpansion

set SOLRCORE="authority"
set EXTRA_SOLRMARC_SETTINGS="-Dsolr.indexer.properties=%MAPPINGS_FILES%"

rem Call the standard script:
call %VUFIND_HOME%\import-marc.bat %1
exit /b %ERRORLEVEL%

:end
