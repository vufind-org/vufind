<?php
/**
 * Command-line tool to begin VuFind installation process
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2012.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Installer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/automation Wiki
 */
define('MULTISITE_NONE', 0);
define('MULTISITE_DIR_BASED', 1);
define('MULTISITE_HOST_BASED', 2);

$baseDir = str_replace('\\', '/', dirname(__FILE__));
$overrideDir = $baseDir . '/local';
$host = $module = '';
$multisiteMode = MULTISITE_NONE;
$basePath = '/vufind';

echo "VuFind has been found in {$baseDir}.\n\n";

// Load user settings if we are not forcing defaults:
if (!isset($argv[1]) || !in_array('--use-defaults', $argv)) {
    $overrideDir = getOverrideDir($overrideDir);
    $module = getModule();
    $basePath = getBasePath($basePath);

    // We assume "single site" mode unless the --multisite switch is set:
    if (isset($argv[1]) && in_array('--multisite', $argv)) {
        $multisiteMode = getMultisiteMode();
    }
    if ($multisiteMode == MULTISITE_HOST_BASED) {
        $host = getHost();
    }
} else {
    // In interactive mode, we initialize the directory as part of the input
    // process; in defaults mode, we need to do it here:
    initializeOverrideDir($overrideDir, true);
}

// Build the Windows start file in case we need it:
buildWindowsConfig($baseDir, $overrideDir, $module);

// Build the import configuration:
buildImportConfig($baseDir, $overrideDir, 'import.properties');
buildImportConfig($baseDir, $overrideDir, 'import_auth.properties');

// Build the custom module, if necessary:
if (!empty($module)) {
    buildModule($baseDir, $module);
}

// Build the final configuration:
buildApacheConfig($baseDir, $overrideDir, $basePath, $module, $multisiteMode, $host);

// Report success:
echo "Apache configuration written to {$overrideDir}/httpd-vufind.conf.\n\n";
echo "You now need to load this configuration into Apache.\n";
getApacheLocation($overrideDir);
if (!empty($host)) {
    echo "Since you are using a host-based multisite configuration, you will also" .
        "\nneed to do some virtual host configuration. See\n" .
        "     http://httpd.apache.org/docs/2.2/vhosts/\n\n";
}
echo "Once the configuration is linked, restart Apache.  You should now be able\n";
echo "to access VuFind at http://localhost{$basePath}\n\n";
echo "For proper use of command line tools, you should also ensure that your\n";
if (empty($module)) {
    echo "VUFIND_HOME and VUFIND_LOCAL_DIR environment variables are set to\n";
    echo "{$baseDir} and {$overrideDir} respectively.\n\n";
} else {
    echo "VUFIND_HOME, VUFIND_LOCAL_MODULES and VUFIND_LOCAL_DIR environment\n";
    echo "variables are set to {$baseDir}, {$module} and {$overrideDir} ";
    echo "respectively.\n\n";
}

/**
 * Display system-specific information for where configuration files are found and/or
 * symbolic links should be created.
 *
 * @return void
 */
function getApacheLocation($overrideDir)
{
    // There is one special case for Windows, and a variety of different
    // Unix-flavored possibilities that all work similarly.
    if (strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN') {   // Windows
        echo "Go to Start -> Apache HTTP Server -> Edit the Apache httpd.conf\n";
        echo "and add this line to your httpd.conf file: \n";
        echo "     Include {$overrideDir}/httpd-vufind.conf\n\n";
        echo "If you are using a bundle like XAMPP and do not have this start\n";
        echo "menu option, you should find and edit your httpd.conf file manually\n";
        echo "(usually in a location like c:\\xampp\\apache\\conf).\n\n";
    } else {
        if (is_dir('/etc/httpd/conf.d')) {                      // Mandriva / RedHat
            $confD = '/etc/httpd/conf.d';
            $httpdConf = '/etc/httpd/conf/httpd.conf';
        } else if (is_dir('/etc/apache2/2.2/conf.d')) {         // Solaris
            $confD = '/etc/apache2/2.2/conf.d';
            $httpdConf = '/etc/apache2/2.2/httpd.conf';
        } else if (is_dir('/etc/apache2/conf.d')) {             // Ubuntu / OpenSUSE
            $confD = '/etc/apache2/conf.d';
            $httpdConf = '/etc/apache2/httpd.conf';
        } else if (is_dir('/opt/local/apache2/conf/extra')) {   // Mac with Mac Ports
            $confD = '/opt/local/apache2/conf/extra';
            $httpdConf = '/opt/local/apache2/conf/httpd.conf';
        } else {
            $confD = '/path/to/apache/conf.d';
            $httpdConf = false;
        }

        // Check if httpd.conf really exists before recommending a specific path;
        // if missing, just use the generic name:
        $httpdConf = ($httpdConf && file_exists($httpdConf))
            ? $httpdConf : 'httpd.conf';

        echo "You can do it in either of two ways:\n\n";
        echo "    a) Add this line to your {$httpdConf} file:\n";
        echo "       Include {$overrideDir}/httpd-vufind.conf\n\n";
        echo "    b) Link the configuration to Apache's conf.d directory like this:";
        echo "\n       ln -s {$overrideDir}/httpd-vufind.conf {$confD}/vufind\n\n";
        echo "Option b is preferable if your platform supports it,\n";
        echo "but option a is more certain to be supported.\n\n";
    }
}

/**
 * Get a base path from the user (or return a default).
 *
 * @param string $basePath Default value
 *
 * @return string
 */
function getBasePath($basePath)
{
    // Get VuFind base path:
    while (true) {
        $basePathInput = getInput(
            "What base path should be used in VuFind's URL? [{$basePath}] "
        );
        if (!empty($basePathInput)) {
            if (!preg_match('/^\/\w*$/', $basePathInput)) {
                echo "Error: Base path must be alphanumeric and start with a "
                    . "slash.\n\n";
            } else {
                return $basePathInput;
            }
        } else {
            return $basePath;
        }
    }
}

/**
 * Initialize the override directory and report success or failure.
 *
 * @param string $dir        Path to attempt to initialize
 * @param bool   $dieOnError Should we die outright if we fail?
 *
 * @return void
 */
function initializeOverrideDir($dir, $dieOnError = false)
{
    $dirStatus = buildDirs(
        array(
            $dir,
            $dir . '/cache',
            $dir . '/config',
            $dir . '/harvest',
            $dir . '/import'
        )
    );
    if ($dieOnError && ($dirStatus !== true)) {
        die("Cannot initialize local override directory: {$dir}\n");
    }
    return $dirStatus === true;
}

/**
 * Get an override directory from the user (or return a default).
 *
 * @param string $overrideDir Default value
 *
 * @return string
 */
function getOverrideDir($overrideDir)
{
    // Get override directory path:
    while (true) {
        $overrideDirInput = getInput(
            "Where would you like to store your local settings? [{$overrideDir}] "
        );
        if (!empty($overrideDirInput)) {
            if (!initializeOverrideDir($overrideDirInput)) {
                echo "Error: Cannot initialize settings in '$overrideDirInput'.\n\n";
            } else {
                return str_replace('\\', '/', realpath($overrideDirInput));
            }
        } else {
            initializeOverrideDir($overrideDir, true);
            return $overrideDir;
        }
    }
}

/**
 * Get the custom module name from the user (or blank for none).
 *
 * @return string
 */
function getModule()
{
    // Get custom module name:
    echo "\nVuFind supports use of a custom module for storing local code ";
    echo "changes.\nIf you do not plan to customize the code, you can ";
    echo "skip this step.\nIf you decide to use a custom module, the name ";
    echo "you choose will be used for\nthe module's directory name and its ";
    echo "PHP namespace.\n";
    while (true) {
        $moduleInput = trim(
            getInput(
                "\nWhat module name would you like to use? [blank for none] "
            )
        );
        $regex = '/[a-zA-Z][0-9a-zA-Z_]*/';
        $illegalModules = array('VuFind', 'VuFindConsole', 'VuFindTest');
        if (in_array($moduleInput, $illegalModules)) {
            echo "\n{$moduleInput} is a reserved name; please try another.\n";
        } else if (empty($moduleInput) || preg_match($regex, $moduleInput)) {
            return $moduleInput;
        } else {
            echo "\nIllegal name: {$moduleInput}; please use alphanumeric text.\n";
        }
    }
}

/**
 * Get the user's preferred multisite mode.
 *
 * @return int
 */
function getMultisiteMode()
{
    echo "\nWhen running multiple VuFind sites against a single installation, you"
        . "need\nto decide how to distinguish between instances.  Choose an option:"
        . "\n\n" . MULTISITE_DIR_BASED
        . ".) Directory-based (i.e. http://server/vufind1 vs. http://server/vufind2)"
        . "\n" . MULTISITE_HOST_BASED
        . ".) Host-based (i.e. http://vufind1.server vs. http://vufind2.server)"
        . "\n\nor enter " . MULTISITE_NONE . " to disable multisite mode.\n";
    $legal = array(MULTISITE_NONE, MULTISITE_DIR_BASED, MULTISITE_HOST_BASED);
    while (true) {
        $input = getInput("\nWhich option do you want? ");
        if (!is_numeric($input) || !in_array(intval($input), $legal)) {
            echo "Invalid selection.";
        } else {
            return intval($input);
        }
    }
}

/**
 * Get the user's hostname preference.
 *
 * @return string
 */
function getHost()
{
    // From http://stackoverflow.com/questions/106179/
    //             regular-expression-to-match-hostname-or-ip-address
    $valid = "/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*"
        . "([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/";
    while (true) {
        $input = getInput("\nPlease enter the hostname for your site: ");
        if (preg_match($valid, $input)) {
            return $input;
        } else {
            echo "Invalid hostname.\n";
        }
    }
}

/**
 * readline() does not exist on Windows.  This is a simple wrapper for portability.
 *
 * @param string $prompt Prompt to display to the user.
 *
 * @return string        User-entered response.
 */
function getInput($prompt)
{
    // Standard function for most uses
    if (function_exists('readline')) {
        $in = readline($prompt);
        return $in;
    } else {
        // Or use our own if it doesn't exist (windows)
        print $prompt;
        $fp = fopen("php://stdin", "r");
        $in = fgets($fp, 4094); // Maximum windows buffer size
        fclose($fp);
        // Seems to keep the carriage return if you don't trim
        return trim($in);
    }
}

/**
 * Generate the Apache configuration.
 *
 * @param string $baseDir     The VuFind base directory
 * @param string $overrideDir The VuFind override directory
 * @param string $basePath    The VuFind URL base path
 * @param string $module      The VuFind custom module name (or empty for none)
 * @param int    $multi       Multisite mode preference
 * @param string $host        Virtual host name (or empty for none)
 *
 * @return void
 */
function buildApacheConfig($baseDir, $overrideDir, $basePath, $module, $multi, $host)
{
    $baseConfig = $baseDir . '/config/vufind/httpd-vufind.conf';
    $config = @file_get_contents($baseConfig);
    if (empty($config)) {
        die("Problem reading {$baseConfig}.\n\n");
    }
    $config = str_replace("/usr/local/vufind/local", "%override-dir%", $config);
    $config = str_replace("/usr/local/vufind", "%base-dir%", $config);
    $config = str_replace("/vufind", "%base-path%", $config);
    $config = str_replace("%override-dir%", $overrideDir, $config);
    $config = str_replace("%base-dir%", $baseDir, $config);
    $config = str_replace("%base-path%", $basePath, $config);
    if (!empty($module)) {
        $config = str_replace(
            "#SetEnv VUFIND_LOCAL_MODULES VuFindLocalTemplate",
            "SetEnv VUFIND_LOCAL_MODULES {$module}", $config
        );
    }

    // In multisite mode, we need to make environment variables conditional:
    switch ($multi) {
    case MULTISITE_DIR_BASED:
        $config = preg_replace(
            '/SetEnv\s+(\w+)\s+(.*)/',
            'SetEnvIf Request_URI "^' . $basePath . '" $1=$2',
            $config
        );
        break;
    case MULTISITE_HOST_BASED:
        $config = preg_replace(
            '/SetEnv\s+(\w+)\s+(.*)/',
            'SetEnvIfNoCase Host ' . str_replace('.', '\.', $host) . ' $1=$2',
            $config
        );
        break;
    }

    if (!@file_put_contents($overrideDir . '/httpd-vufind.conf', $config)) {
        die("Problem writing {$overrideDir}/httpd-vufind.conf.\n\n");
    }
}

/**
 * Build the Windows-specific startup configuration.
 *
 * @param string $baseDir     The VuFind base directory
 * @param string $overrideDir The VuFind override directory
 * @param string $module      The VuFind custom module name (or empty for none)
 *
 * @return void
 */
function buildWindowsConfig($baseDir, $overrideDir, $module)
{
    $batch = "@set VUFIND_HOME={$baseDir}\n" .
        "@set VUFIND_LOCAL_DIR={$overrideDir}\n" .
        (empty($module) ? '' : "@set VUFIND_LOCAL_MODULES={$module}\n") .
        "@call run_vufind.bat %1 %2 %3 %4 %5 %6 %7 %8 %9";
    if (!@file_put_contents($baseDir . '/vufind.bat', $batch)) {
        die("Problem writing {$baseDir}/vufind.bat.\n\n");
    }
}

/**
 * Configure a SolrMarc properties file.
 *
 * @param string $baseDir     The VuFind base directory
 * @param string $overrideDir The VuFind override directory
 * @param string $filename    The properties file to configure
 *
 * @return void
 */
function buildImportConfig($baseDir, $overrideDir, $filename)
{
    $import = @file_get_contents($baseDir . '/import/' . $filename);
    $import = str_replace("/usr/local/vufind", $baseDir, $import);
    $import = preg_replace(
        "/^\s*solrmarc.path\s*=.*$/m",
        "solrmarc.path = {$overrideDir}/import|{$baseDir}/import", $import
    );
    if (!@file_put_contents($overrideDir . '/import/' . $filename, $import)) {
        die("Problem writing {$overrideDir}/import/{$filename}.\n\n");
    }
}

/**
 * Build a set of directories.
 *
 * @param array $dirs Directories to build
 *
 * @return bool|string True on success, name of problem directory on failure
 */
function buildDirs($dirs)
{
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !@mkdir($dir)) {
            return $dir;
        }
    }
    return true;
}

/**
 * Build the module for storing local code changes.
 *
 * @param string $baseDir The VuFind base directory
 * @param string $module  The name of the new module (assumed valid!)
 *
 * @return void
 */
function buildModule($baseDir, $module)
{
    // Create directories:
    $moduleDir = $baseDir . '/module/' . $module;
    $dirStatus = buildDirs(
        array(
            $moduleDir,
            $moduleDir . '/config',
            $moduleDir . '/src',
            $moduleDir . '/src/' . $module
        )
    );
    if ($dirStatus !== true) {
        die("Problem creating {$dirStatus}.\n");
    }

    // Copy configuration:
    $configFile = $baseDir . '/module/VuFindLocalTemplate/config/module.config.php';
    $config = @file_get_contents($configFile);
    if (!$config) {
        die("Problem reading {$configFile}.\n");
    }
    $success = @file_put_contents(
        $moduleDir . '/config/module.config.php',
        str_replace('VuFindLocalTemplate', $module, $config)
    );
    if (!$success) {
        die("Problem writing {$moduleDir}/config/module.config.php.\n");
    }

    // Copy PHP code:
    $moduleFile = $baseDir . '/module/VuFindLocalTemplate/Module.php';
    $contents = @file_get_contents($moduleFile);
    if (!$contents) {
        die("Problem reading {$moduleFile}.\n");
    }
    $success = @file_put_contents(
        $moduleDir . '/Module.php',
        str_replace('VuFindLocalTemplate', $module, $contents)
    );
    if (!$success) {
        die("Problem writing {$moduleDir}/Module.php.\n");
    }
}
