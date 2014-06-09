@echo off
rem Startup script for the VuFind Jetty Server under Windows
rem
rem Configuration variables
rem
rem VUFIND_HOME
rem   Home of the VuFind installation.
rem
rem SOLR_HOME
rem   Home of the Solr installation.
rem
rem JAVA_HOME
rem   Home of Java installation.
rem
rem JAVA
rem   Command to invoke Java. If not set, %JAVA_HOME%\bin\java will be
rem   used.
rem
rem JAVA_OPTIONS
rem   Extra options to pass to the JVM
rem
rem JETTY_HOME
rem   Where Jetty is installed. If not set, the script will try to guess it based
rem   on the SOLR_HOME setting. The java system property "jetty.home" will be
rem   set to this value for use by configure.xml files, f.e.:
rem
rem    <Arg><SystemProperty name="jetty.home" default="."/>\webapps\jetty.war</Arg>
rem
rem JETTY_LOG
rem   The path where Jetty will store the log files
rem
rem JETTY_PORT
rem   Override the default port for Jetty servers. If not set then the
rem   default value in the xml configuration file will be used. The java
rem   system property "jetty.port" will be set to this value for use in
rem   configure.xml files. For example, the following idiom is widely
rem   used in the demo config files to respect this property in Listener
rem   configuration elements:
rem
rem    <Set name="Port"><SystemProperty name="jetty.port" default="8080"/></Set>
rem
rem   Note: that the config file could ignore this property simply by saying:
rem
rem    <Set name="Port">8080</Set>
rem
rem JETTY_ARGS
rem   The default arguments to pass to jetty.

rem Make sure that environment edits are local and that we have access to the
rem Windows command extensions.
setlocal enableextensions
if not errorlevel 1 goto extensionsokay
echo Unable to enable Windows command extensions.
goto end
:extensionsokay

rem Get the action and shift the parameter list (in case we implement configuration
rem files in the future)
set ACTION=%1
shift

rem Set Performance options for JETTY
rem ========================================================================
rem -Xms    Sets the initial heap size for when the JVM starts.
rem -Xmx    Sets the maximum heap size.
rem If you often get a "catalog error" or need to restart vufind you
rem may need to increase the Xmx value if your system has the memory
rem to support it. For example, on a system with 4GB of memory that is
rem dedicated to running Vufind you may want to set the Xmx value to
rem 2048m or 3,072m.
rem
rem IMPORTANT NOTE: You may want to skip setting the Xms value
rem so that JAVA / Vufind will only use what it needs - potentialy
rem leaving more memory for the rest of the system. To see the JVM memory
rem usage visit your solr URL, possibly the same URL as your vufind,
rem instance but appended with :8080/solr/
rem
rem The most important factors for determining the amount of memory
rem that you will need to allocate are the number of records you have
rem indexed, and how much traffic your site receives. It may also be
rem beneficial to limit or block crawlers and robots as they can,
rem at times, generate so many requests that your site has poor
rem performance for your human patrons
rem
rem For more information on tuning vufind and java, see the
rem vufind wiki article at https://vufind.org/wiki/performance
rem and http://www.oracle.com/technetwork/java/gc-tuning-5-138395.html
rem
rem Some example settings:
rem JAVA_OPTIONS="-server -Xms1048576k -Xmx1048576k -XX:+UseParallelGC -XX:NewRatio=5"
rem JAVA_OPTIONS="-server -Xmx1048576k -XX:+UseParallelGC -XX:NewRatio=5"
rem JAVA_OPTIONS="-server -Xms1024m -Xmx1024m -XX:+UseParallelGC -XX:NewRatio=5"
rem JAVA_OPTIONS="-server -Xmx1024m -XX:+UseParallelGC -XX:NewRatio=5"
rem ========================================================================
if not "!%JAVA_OPTIONS%!"=="!!" goto javaoptionsset
set JAVA_OPTIONS=-server -Xms1024m -Xmx1024m -XX:+UseParallelGC -XX:NewRatio=5
:javaoptionsset

rem Set VUFIND_HOME (if not already set)
if not "!%VUFIND_HOME%!"=="!!" goto vufindhomeset
set VUFIND_HOME=c:\vufind
:vufindhomeset

rem Set SOLR_HOME
if not "!%SOLR_HOME%!"=="!!" goto solrhomeset
set SOLR_HOME=%VUFIND_HOME%\solr
:solrhomeset

rem Set JETTY_HOME
if not "!%JETTY_HOME%!"=="!!" goto jettyhomeset
set JETTY_HOME=%SOLR_HOME%\jetty
:jettyhomeset

rem Set Jetty's Logging Directory
if not "!%JETTY_LOG%!"=="!!" goto jettylogset
set JETTY_LOG=%JETTY_HOME%\logs
:jettylogset

rem Jetty's hallmark
set JETTY_INSTALL_TRACE_FILE=start.jar

rem Check that jetty is where we think it is
if exist "%JETTY_HOME%\%JETTY_INSTALL_TRACE_FILE%" goto jettyokay
echo ** ERROR: Oops! Jetty doesn't appear to be installed in %JETTY_HOME%
echo ** ERROR:  %JETTY_HOME%\%JETTY_INSTALL_TRACE_FILE% is not readable!
goto end
:jettyokay

rem Advanced configuration files not currently supported -- just run standard server
set CONFIGS=%JETTY_HOME%\etc\jetty.xml

rem Take a stab at detecting JAVA_HOME before we die
if not "!%JAVA_HOME%!"=="!!" goto javahomeokay
echo Detecting Java...
set KeyName=HKEY_LOCAL_MACHINE\SOFTWARE\JavaSoft\Java Development Kit
set Cmd=reg query "%KeyName%" /s
for /f "tokens=2*" %%i in ('%Cmd% ^| findstr "JavaHome"') do set JAVA_HOME=%%j

rem Make sure JAVA_HOME is set
if not "!%JAVA_HOME%!"=="!!" goto javahomeokay
echo ** ERROR: The JAVA_HOME environment variable must be set.
goto end
:javahomeokay

rem Choose default Java command if it was not set externally:
if not "!%JAVA%!"=="!!" goto javaset
set JAVA=%JAVA_HOME%\bin\java
:javaset

rem See if JETTY_PORT is defined
if "!%JETTY_PORT%!"=="!!" goto skipjettyport
set JAVA_OPTIONS=%JAVA_OPTIONS% -Djetty.port=%JETTY_PORT%
:skipjettyport

rem Add Solr values to command line
if "!%SOLR_HOME%!"=="!!" goto skipsolrhome
set JAVA_OPTIONS=%JAVA_OPTIONS% -Dsolr.solr.home=%SOLR_HOME%
:skipsolrhome

rem Set Jetty Logging Directory
if "!%JETTY_LOG%!"=="!!" goto skipjettylog
set JAVA_OPTIONS=%JAVA_OPTIONS% -Djetty.logs=%JETTY_LOG%
:skipjettylog

rem Add jetty properties to Java VM options.
set JAVA_OPTIONS=%JAVA_OPTIONS% -Djetty.home=%JETTY_HOME%

rem This is how the Jetty server will be started
set RUN_CMD="%JAVA%" %JAVA_OPTIONS% -jar %JETTY_HOME%\%JETTY_INSTALL_TRACE_FILE% %JETTY_ARGS% %CONFIGS%

rem Perform the requested action
if %ACTION%!==start! goto startvufind
if %ACTION%!==check! goto checkvufind

rem Unrecognized action -- display help text
goto usage

rem Logic for "start" action:
:startvufind
echo Running command: %RUN_CMD%
%RUN_CMD%
goto end

rem Logic for "check" action:
:checkvufind
echo Checking arguments to VuFind:
echo VUFIND_HOME    =  %VUFIND_HOME%
echo SOLR_HOME      =  %SOLR_HOME%
echo JETTY_HOME     =  %JETTY_HOME%
echo JETTY_LOG      =  %JETTY_LOG%
echo JETTY_PORT     =  %JETTY_PORT%
echo CONFIGS        =  %CONFIGS%
echo JAVA_OPTIONS   =  %JAVA_OPTIONS%
echo JAVA           =  %JAVA%
echo RUN_CMD        =  %RUN_CMD%
echo.
goto usage

:usage
echo Usage: vufind {start / check}
echo.
echo Note: To stop VuFind, open the window where it is running and hit Ctrl-C.
goto end

:end
rem We're all done -- close down the local environment.
endlocal