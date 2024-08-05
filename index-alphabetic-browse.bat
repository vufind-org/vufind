@echo off
rem #####################################################
rem Make sure that environment edits are local and that we have access to the
rem Windows command extensions.
rem #####################################################
setlocal enableextensions
if not errorlevel 1 goto extensionsokay
echo Unable to enable Windows command extensions.
goto end
:extensionsokay

rem ##################################################
rem # Set SOLR_HOME
rem ##################################################
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
if not "!%SOLR_HOME%!"=="!!" goto solrhomefound
set SOLR_HOME=%VUFIND_HOME%\solr\vufind
:solrhomefound

rem #####################################################
rem # Build java command
rem #####################################################
if not "!%JAVA_HOME%!"=="!!" goto javahomefound
set JAVA=java
goto javaset
:javahomefound
set JAVA="%JAVA_HOME%\bin\java"
:javaset

rem This can point to an external Solr in e.g. a Docker container
if not "!%SOLR_JAR_PATH%!"=="!!" goto solrjarpathfound
set SOLR_JAR_PATH=%SOLR_HOME%\..\vendor
:solrjarpathfound

cd %VUFIND_HOME%\import
setlocal enabledelayedexpansion
set SOLRMARC_MATCHCOUNT=x
for %%a in (solrmarc_core*.jar) do (
  set SOLRMARC_CLASSPATH=%%a
  set SOLRMARC_MATCHCOUNT=!SOLRMARC_MATCHCOUNT!x
)
setlocal disabledelayedexpansion
rem Make sure we found one, and only one, SolrMarc jar file
if "%SOLRMARC_MATCHCOUNT%"=="xx" goto onesolrmarcfound
if "%SOLRMARC_MATCHCOUNT%"=="x" goto nosolrmarcfound
echo Error: more than one solrmarc_core*.jar in import; exiting.
goto end
:nosolrmarcfound
echo "Error: could not find solrmarc_core*.jar in import; exiting.
goto end
:onesolrmarcfound
SET CLASSPATH="browse-indexing.jar;%SOLRMARC_CLASSPATH%;%VUFIND_HOME%\import\lib\*;%SOLR_HOME%\jars\*;%SOLR_JAR_PATH%\modules\analysis-extras\lib\*;%SOLR_JAR_PATH%\server\solr-webapp\webapp\WEB-INF\lib\*"

SET bib_index=%SOLR_HOME%\biblio\index
SET auth_index=%SOLR_HOME%\authority\index
SET index_dir=%SOLR_HOME%\alphabetical_browse

rem #####################################################
rem If we're being called for the build_browse function, jump there now:
rem #####################################################
if "!%1!"=="!build_browse!" goto build_browse

rem #####################################################
rem If we got this far, we want to go through the main logic:
rem #####################################################
if exist %index_dir% goto nomakeindexdir
mkdir "%index_dir%"
:nomakeindexdir

rem These parameters should match the ones in solr/vufind/biblio/conf/solrconfig.xml - BrowseRequestHandler
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse hierarchy hierarchy_browse
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse title title_fullStr 1 "-Dbib_field_iterator=org.vufind.solr.indexing.StoredFieldIterator -Dsortfield=title_sort -Dvaluefield=title_fullStr -Dbrowse.normalizer=org.vufind.util.TitleNormalizer"
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse topic topic_browse
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse author author_browse
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse lcc callnumber-raw 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer"
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse dewey dewey-raw 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer"
goto end

rem Function to process a single browse index:
:build_browse
shift
SET browse=%1
SET field=%2
SET jvmopts=%4

rem Strip double quotes from JVM options:
SET jvmopts=###%jvmopts%###
SET jvmopts=%jvmopts:"###=%
SET jvmopts=%jvmopts:###"=%
SET jvmopts=%jvmopts:###=%

echo Building browse index for %browse%...

set args="%bib_index%" "%field%" "%browse%.tmp"
if "!%3!"=="!1!" goto skipauth
set args="%bib_index%" "%field%" "%auth_index%" "%browse%.tmp"
:skipauth

rem Get the browse headings from Solr
%JAVA% %jvmopts% -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp %CLASSPATH% org.vufind.solr.indexing.PrintBrowseHeadings %args%

rem Sort the browse headings
sort %browse%.tmp /o sorted-%browse%.tmp /rec 65535

rem Remove duplicate lines
php %VUFIND_HOME%\util\dedupe.php "sorted-%browse%.tmp" "unique-%browse%.tmp"

rem Build the SQLite database
%JAVA% -Dfile.encoding="UTF-8" -cp %CLASSPATH% org.vufind.solr.indexing.CreateBrowseSQLite "unique-%browse%.tmp" "%browse%_browse.db"

rem Clear up temp files
del /q *.tmp > nul

rem Move the new database to the index directory
move "%browse%_browse.db" "%index_dir%\%browse%_browse.db-updated" > nul

rem Indicate that the new database is ready for use
echo OK > "%index_dir%\%browse%_browse.db-ready"
:end
