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
$baseDir = str_replace('\\', '/', dirname(__FILE__));
$overrideDir = $baseDir . '/local';
$module = '';
$basePath = '/vufind';

echo "VuFind has been found in {$baseDir}.\n\n";

if (!isset($argv[1]) || !in_array('--use-defaults', $argv)) {
    // Get override directory path:
    while (true) {
        $overrideDirInput = getInput(
            "Where would you like to store your local settings? [{$overrideDir}] "
        );
        if (!empty($overrideDirInput)) {
            if (!is_dir($overrideDirInput) && !@mkdir($overrideDirInput)) {
                echo "Error: Cannot create directory '$overrideDirInput'.\n\n";
            } else {
                $overrideDir = str_replace('\\', '/', realpath($overrideDirInput));
                break;
            }
        } else {
            break;
        }
    }
    
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
            $module = $moduleInput;
            break;
        } else {
            echo "\nIllegal name: {$moduleInput}; please use alphanumeric text.\n";
        }
    }

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
                $basePath = $basePathInput;
                break;
            }
        } else {
            break;
        }
    }
}

// Build the Windows start file in case we need it:
$batch = "@set VUFIND_HOME={$baseDir}\n" .
    "@set VUFIND_LOCAL_DIR={$overrideDir}\n" .
    (empty($module) ? '' : "@set VUFIND_LOCAL_MODULES={$module}\n") .
    "@call run_vufind.bat %1 %2 %3 %4 %5 %6 %7 %8 %9";
if (!@file_put_contents($baseDir . '/vufind.bat', $batch)) {
    die("Problem writing {$baseDir}/vufind.bat.\n\n");
}

// Build the import configuration:
build_import_config($baseDir, $overrideDir, 'import.properties');
build_import_config($baseDir, $overrideDir, 'import_auth.properties');

// Build the custom module, if necessary:
if (!empty($module)) {
    build_module($baseDir, $module);
}

// Build the final configuration:
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
if (!@file_put_contents($overrideDir . '/httpd-vufind.conf', $config)) {
    die("Problem writing {$overrideDir}/httpd-vufind.conf.\n\n");
}

// Report success:
echo "Apache configuration written to {$overrideDir}/httpd-vufind.conf.\n\n";
echo "You now need to load this configuration into Apache.\n";
echo "You can do it in either of two ways:\n\n";
echo "    a) Add this line to your httpd.conf file:\n";
echo "       Include {$overrideDir}/httpd-vufind.conf\n\n";
echo "    b) Link the configuration to Apache's conf.d directory like this:\n";
echo "       ln -s {$overrideDir}/httpd-vufind.conf "
    . "/etc/apache2/conf.d/vufind\n\n";
echo "Option b is preferable if your platform supports it (paths may vary),\n";
echo "but option a is more certain to be supported.\n\n";
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
 * Configure a SolrMarc properties file.
 *
 * @param string $baseDir     The VuFind base directory
 * @param string $overrideDir The VuFind override directory
 * @param string $filename    The properties file to configure
 *
 * @return void
 */
function build_import_config($baseDir, $overrideDir, $filename)
{
    $import = @file_get_contents($baseDir . '/import/' . $filename);
    $import = str_replace("/usr/local/vufind", $baseDir, $import);
    $import = preg_replace(
        "/^\s*solrmarc.path\s*=.*$/m",
        "solrmarc.path = {$overrideDir}/import|{$baseDir}/import", $import
    );
    if (!is_dir($overrideDir . '/import')) {
        if (!@mkdir($overrideDir . '/import')) {
            die("Problem creating {$overrideDir}/import directory.\n\n");
        }
    }
    if (!@file_put_contents($overrideDir . '/import/' . $filename, $import)) {
        die("Problem writing {$overrideDir}/import/{$filename}.\n\n");
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
function build_module($baseDir, $module)
{
    // Create directories:
    $moduleDir = $baseDir . '/module/' . $module;
    $dirsToCreate = array(
        $moduleDir,
        $moduleDir . '/config',
        $moduleDir . '/src',
        $moduleDir . '/src/' . $module
    );
    foreach ($dirsToCreate as $dir) {
        if (!is_dir($dir) && !@mkdir($dir)) {
            die("Problem creating {$dir}.\n");
        }
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