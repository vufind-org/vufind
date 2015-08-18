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
rem VUFIND_HOME not set -- try to call vufind.bat to 
rem fix the problem before we give up completely
if exist vufind.bat goto usevufindbat
rem If vufind.bat doesn't exist, the user hasn't run the installer yet.
echo ERROR: vufind.bat does not exist -- could not set up environment.
echo Please run install.php to correct this problem.
goto end
:usevufindbat
call vufind > nul
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomefound
echo You need to set the VUFIND_HOME environmental variable before running this script.
goto end
:vufindhomefound
if not "!%SOLR_HOME%!"=="!!" goto solrhomefound
set SOLR_HOME=%VUFIND_HOME%\solr
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

cd %VUFIND_HOME%\import
SET CLASSPATH="browse-indexing.jar;..\solr\lib\*"

SET bib_index=..\solr\biblio\index
SET auth_index=..\solr\authority\index
SET index_dir=..\solr\alphabetical_browse

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

call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse hierarchy hierarchy_browse
call %VUFIND_HOME%\index-alphabetic-browse.bat build_browse title title_fullStr 1 "-Dbibleech=StoredFieldLeech -Dsortfield=title_sort -Dvaluefield=title_fullStr"
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

rem Extract lines from Solr
java %jvmopts% -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp %CLASSPATH% PrintBrowseHeadings %args%

rem Sort lines
sort %browse%.tmp /o sorted-%browse%.tmp /rec 65535

rem Remove duplicate lines
php %VUFIND_HOME%\util\dedupe.php "sorted-%browse%.tmp" "unique-%browse%.tmp"

rem Build database file
java -Dfile.encoding="UTF-8" -cp %CLASSPATH% CreateBrowseSQLite "unique-%browse%.tmp" "%browse%_browse.db"

del /q *.tmp > nul

move "%browse%_browse.db" "%index_dir%\%browse%_browse.db-updated" > nul
echo OK > "%index_dir%\%browse%_browse.db-ready"
:end
