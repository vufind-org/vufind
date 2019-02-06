<?php
/**
 * Command-line tool to begin VuFind installation process
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Installer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/installation Wiki
 */

require_once __DIR__ . '/vendor/autoload.php';

use Zend\Console\Getopt;

define('MULTISITE_NONE', 0);
define('MULTISITE_DIR_BASED', 1);
define('MULTISITE_HOST_BASED', 2);

$baseDir = str_replace('\\', '/', dirname(__FILE__));
$overrideDir = $baseDir . '/local';
$host = $module = '';
$multisiteMode = MULTISITE_NONE;
$basePath = '/vufind';

try {
    $opts = new Getopt(
        array(
        'use-defaults' =>
           'Use VuFind Defaults to Configure (ignores any other arguments passed)',
        'overridedir=s' =>
           "Where would you like to store your local settings? [{$baseDir}/local]",
        'module-name=s' =>
           'What module name would you like to use? Use disabled, to not use',
        'basepath=s' =>
           "What base path should be used in VuFind's URL? [{$basePath}]",
        'multisite-w' =>
           'Specify we are going to setup a multisite. Options: directory and host',
        'hostname=s' =>
            'Specify the hostname for the VuFind Site, When multisite=host',
        'non-interactive' =>
            'Use settings if provided via arguments, otherwise use defaults',
      )
    );
    $opts->parse();
} catch (Exception $e) {
    echo is_callable([$e, 'getUsageMessage'])
        ? $e->getUsageMessage() : $e->getMessage() . "\n";
    exit;
}

echo "VuFind has been found in {$baseDir}.\n\n";

// Are we allowing user interaction?
$interactive = !$opts->getOption('non-interactive');
$userInputNeeded = array();

// Load user settings if we are not forcing defaults:
if (!$opts->getOption('use-defaults')) {
    if ($opts->getOption('overridedir')) {
        $overrideDir = $opts->getOption('overridedir');
    } else if ($interactive) {
        $userInputNeeded['overrideDir'] = true;
    }
    if ($opts->getOption('module-name')) {
        if ($opts->getOption('module-name') !== 'disabled') {
            $module = $opts->getOption('module-name');
            if (($result = validateModules($module)) !== true) {
                die($result . "\n");
            }
        }
    } else if ($interactive) {
        $userInputNeeded['module'] = true;
    }

    if ($opts->getOption('basepath')) {
        $basePath = $opts->getOption('basepath');
        if (($result = validateBasePath($basePath, true)) !== true) {
            die($result . "\n");
        }
    } else if ($interactive) {
        $userInputNeeded['basePath'] = true;
    }

    // We assume "single site" mode unless the --multisite switch is set:
    if ($opts->getOption('multisite')) {
        if ($opts->getOption('multisite') === 'directory') {
            $multisiteMode = MULTISITE_DIR_BASED;
        } else if ($opts->getOption('multisite') === 'host') {
            $multisiteMode = MULTISITE_HOST_BASED;
        } else if (($bad = $opts->getOption('multisite')) && $bad !== true) {
            die('Unexpected multisite mode: ' . $bad . "\n");
        } else if ($interactive) {
            $userInputNeeded['multisiteMode'] = true;
        }
    }

    // Now that we've validated as many parameters as possible, retrieve
    // user input where needed.
    if (isset($userInputNeeded['overrideDir'])) {
        $overrideDir = getOverrideDir($overrideDir);
    }
    if (isset($userInputNeeded['module'])) {
        $module = getModule();
    }
    if (isset($userInputNeeded['basePath'])) {
        $basePath = getBasePath($basePath);
    }
    if (isset($userInputNeeded['multisiteMode'])) {
        $multisiteMode = getMultisiteMode();
    }

    // Load supplemental multisite parameters:
    if ($multisiteMode == MULTISITE_HOST_BASED) {
        if ($opts->getOption('hostname')) {
             $host = $opts->getOption('hostname');
        } else if ($interactive) {
             $host = getHost();
        }
    }
}

// Make sure the override directory is initialized (using defaults or CLI
// parameters will not have initialized it yet; attempt to reinitialize it
// here is harmless if it was already initialized in interactive mode):
initializeOverrideDir($overrideDir, true);

// Normalize the module setting to remove whitespace:
$module = preg_replace('/\s/', '', $module);

// Build the Windows start file in case we need it:
buildWindowsConfig($baseDir, $overrideDir, $module);

// Build the import configuration:
buildImportConfig($baseDir, $overrideDir, 'import.properties');
buildImportConfig($baseDir, $overrideDir, 'import_auth.properties');

// Build the custom module, if necessary:
if (!empty($module)) {
    buildModules($baseDir, $module);
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
if ('/' == $basePath) {
    echo "Since you are installing VuFind at the root of your domain, you will also"
        . "\nneed to edit your Apache configuration to change DocumentRoot to:\n"
        . $baseDir . "/public\n\n";
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
 * @param string $overrideDir Path to VuFind's local override directory
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
        } else if (is_dir('/etc/apache2/conf-enabled')) {   // new Ubuntu / OpenSUSE
            $confD = '/etc/apache2/conf-enabled';
            $httpdConf = '/etc/apache2/apache2.conf';
        } else if (is_dir('/etc/apache2/conf.d')) {         // old Ubuntu / OpenSUSE
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

        // Suggest a symlink name based on the local directory, so if running in
        // multisite mode, we don't use the same symlink for multiple instances:
        $symlink = basename($overrideDir);
        $symlink = ($symlink == 'local') ? 'vufind' : ('vufind-' . $symlink);
        $symlink .= '.conf';

        echo "You can do it in either of two ways:\n\n";
        echo "    a) Add this line to your {$httpdConf} file:\n";
        echo "       Include {$overrideDir}/httpd-vufind.conf\n\n";
        echo "    b) Link the configuration to Apache's config directory like this:";
        echo "\n       ln -s {$overrideDir}/httpd-vufind.conf {$confD}/{$symlink}\n";
        echo "\nOption b is preferable if your platform supports it,\n";
        echo "but option a is more certain to be supported.\n\n";
    }
}

/**
 * Validate a base path. Returns true on success, message on failure.
 *
 * @param string $basePath   String to validate.
 * @param bool   $allowEmpty Are empty values acceptable?
 *
 * @return bool|string
 */
function validateBasePath($basePath, $allowEmpty = false)
{
    if ($allowEmpty && empty($basePath)) {
        return true;
    }
    return preg_match('/^\/\w*$/', $basePath)
        ? true
        : 'Error: Base path must be alphanumeric and start with a slash.';
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
            if (($result = validateBasePath($basePathInput)) !== true) {
                echo "$result\n\n";
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
            return $overrideDir;
        }
    }
}

/**
 * Validate a comma-separated list of module names. Returns true on success, message
 * on failure.
 *
 * @param string $modules Module name to validate.
 *
 * @return bool|string
 */
function validateModules($modules)
{
    foreach (explode(',', $modules) as $module) {
        $result = validateModule(trim($module));
        if ($result !== true) {
            return $result;
        }
    }
    return true;
}

/**
 * Validate the custom module name. Returns true on success, message on failure.
 *
 * @param string $module Module name to validate.
 *
 * @return bool|string
 */
function validateModule($module)
{
    $regex = '/^[a-zA-Z][0-9a-zA-Z_]*$/';
    $illegalModules = array(
        'VuFind', 'VuFindAdmin', 'VuFindConsole', 'VuFindDevTools',
        'VuFindLocalTemplate', 'VuFindSearch', 'VuFindTest', 'VuFindTheme',
    );
    if (in_array($module, $illegalModules)) {
        return "{$module} is a reserved module name; please try another.";
    } else if (empty($module) || preg_match($regex, $module)) {
        return true;
    } else {
        return "Illegal name: {$module}; please use alphanumeric text.";
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
        if (($result = validateModules($moduleInput)) === true) {
            return $moduleInput;
        }
        echo "\n$result\n";
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
 * Validate the user's hostname input. Returns true on success, message on failure.
 *
 * @param string $host String to check
 *
 * @return bool|string
 */
function validateHost($host)
{
    // From http://stackoverflow.com/questions/106179/
    //             regular-expression-to-match-hostname-or-ip-address
    $valid = "/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*"
        . "([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/";
    return preg_match($valid, $host)
        ? true
        : 'Invalid hostname.';
}

/**
 * Get the user's hostname preference.
 *
 * @return string
 */
function getHost()
{
    while (true) {
        $input = getInput("\nPlease enter the hostname for your site: ");
        if (($result = validateHost($input)) === true) {
            return $input;
        } else {
            echo "$result\n";
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
    return \Zend\Console\Prompt\Line::prompt($prompt, true);
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
    $config = str_replace('/usr/local/vufind/local', '%override-dir%', $config);
    $config = str_replace('/usr/local/vufind', '%base-dir%', $config);
    $config = preg_replace('|([^/])\/vufind|', '$1%base-path%', $config);
    $config = str_replace('%override-dir%', $overrideDir, $config);
    $config = str_replace('%base-dir%', $baseDir, $config);
    $config = str_replace('%base-path%', $basePath, $config);
    // Special cases for root basePath:
    if ('/' == $basePath) {
        $config = str_replace('//', '/', $config);
        $config = str_replace('Alias /', '#Alias /', $config);
    }
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
        if (($result = validateHost($host)) !== true) {
            die($result . "\n");
        }
        $config = preg_replace(
            '/SetEnv\s+(\w+)\s+(.*)/',
            'SetEnvIfNoCase Host ' . str_replace('.', '\.', $host) . ' $1=$2',
            $config
        );
        break;
    }

    $target = $overrideDir . '/httpd-vufind.conf';
    if (file_exists($target)) {
        $bak = $target . '.bak.' . time();
        copy($target, $bak);
        echo "Backed up existing Apache configuration to $bak.\n";
    }
    if (!@file_put_contents($target, $config)) {
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
        (empty($module) ? '' : "@set VUFIND_LOCAL_MODULES={$module}\n");
    if (!@file_put_contents($baseDir . '/env.bat', $batch)) {
        die("Problem writing {$baseDir}/env.bat.\n\n");
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
    $target = $overrideDir . '/import/' . $filename;
    if (file_exists($target)) {
        echo "Warning: $target already exists; skipping file creation.\n";
    } else {
        $import = @file_get_contents($baseDir . '/import/' . $filename);
        $import = str_replace("/usr/local/vufind", $baseDir, $import);
        $import = preg_replace(
            "/^\s*solrmarc.path\s*=.*$/m",
            "solrmarc.path = {$overrideDir}/import|{$baseDir}/import", $import
        );
        if (!@file_put_contents($target, $import)) {
            die("Problem writing {$overrideDir}/import/{$filename}.\n\n");
        }
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
 * Make sure all modules exist (and create them if they do not.
 *
 * @param string $baseDir The VuFind base directory
 * @param string $modules The comma-separated list of modules (assumed valid!)
 *
 * @return void
 */
function buildModules($baseDir, $modules)
{
    foreach (explode(',', $modules) as $module) {
        $moduleDir = $baseDir . '/module/' . $module;
        // Is module missing? If so, create it from the template:
        if (!file_exists($moduleDir . '/Module.php')) {
            buildModule($baseDir, $module);
        }
    }
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
