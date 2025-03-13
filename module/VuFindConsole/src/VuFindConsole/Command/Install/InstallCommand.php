<?php

/**
 * Console command: VuFind installer.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Install;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use function in_array;
use function intval;

/**
 * Console command: VuFind installer.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'install/install',
    description: 'VuFind® installer'
)]
class InstallCommand extends Command
{
    public const MULTISITE_NONE = 0;
    public const MULTISITE_DIR_BASED = 1;
    public const MULTISITE_HOST_BASED = 2;

    /**
     * Base directory of VuFind installation.
     *
     * @var string
     */
    protected $baseDir;

    /**
     * Local settings directory for VuFind installation.
     *
     * @var string
     */
    protected $overrideDir;

    /**
     * Hostname of VuFind installation (used for host-based multi-site).
     *
     * @var string
     */
    protected $host = '';

    /**
     * Custom local code module name (if any).
     *
     * @var string
     */
    protected $module = '';

    /**
     * Active multi-site mode.
     *
     * @var int
     */
    protected $multisiteMode = self::MULTISITE_NONE;

    /**
     * Base path for VuFind URLs.
     *
     * @var string
     */
    protected $basePath = '/vufind';

    /**
     * Solr port to use.
     *
     * @var string
     */
    protected $solrPort = '8983';

    /**
     * Should we make backups of existing files?
     *
     * @var bool
     */
    protected $makeBackups = true;

    /**
     * Should we display the Apache setup help messages?
     *
     * @var bool
     */
    protected $showApacheHelp = false;

    /**
     * Constructor
     *
     * @param string|null $name The name of the command; passing null means it must
     * be set in configure()
     */
    public function __construct($name = null)
    {
        $this->baseDir = str_replace(
            '\\',
            '/',
            realpath(__DIR__ . '/../../../../../../')
        );
        $this->overrideDir = $this->baseDir . '/local';
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Set up (or modify) initial VuFind® installation.')
            ->addOption(
                'use-defaults',
                null,
                InputOption::VALUE_NONE,
                'Use VuFind® defaults to configure '
                . '(ignores any other arguments passed)'
            )->addOption(
                'overridedir',
                null,
                InputOption::VALUE_REQUIRED,
                'Where would you like to store your local settings?'
                . " (defaults to {$this->overrideDir} when --non-interactive is set)"
            )->addOption(
                'module-name',
                null,
                InputOption::VALUE_REQUIRED,
                'What module name would you like to use? Specify "disabled" to skip'
            )->addOption(
                'basepath',
                null,
                InputOption::VALUE_REQUIRED,
                'What base path should be used in VuFind®\'s URL?'
                . " (defaults to {$this->baseDir} when --non-interactive is set)"
            )->addOption(
                'multisite',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify we are going to setup a multisite. '
                . 'Options: directory and host',
                false
            )->addOption(
                'hostname',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the hostname for the VuFind® Site, when multisite=host'
            )->addOption(
                'solr-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'Port number to use for Solr'
                . " (defaults to {$this->solrPort} when --non-interactive is set)"
            )->addOption(
                'non-interactive',
                null,
                InputOption::VALUE_NONE,
                'Use settings if provided via arguments, otherwise use defaults'
            )->addOption(
                'skip-backups',
                null,
                InputOption::VALUE_NONE,
                'Overwrite existing files without creating backups'
            )->addOption(
                'no-apache-help',
                null,
                InputOption::VALUE_NONE,
                'Skip displaying of Apache configuration help messages when installation is completed'
            );
    }

    /**
     * Write file contents to disk.
     *
     * @param string $filename Filename
     * @param string $content  Content
     *
     * @return bool
     */
    protected function writeFileToDisk($filename, $content)
    {
        return @file_put_contents($filename, $content);
    }

    /**
     * Get instructions for editing the Apache configuration under Windows.
     *
     * @return string
     */
    protected function getWindowsApacheMessage()
    {
        return "Go to Start -> Apache HTTP Server -> Edit the Apache httpd.conf\n"
            . "and add this line to your httpd.conf file: \n"
            . "     Include {$this->overrideDir}/httpd-vufind.conf\n\n"
            . "If you are using a bundle like XAMPP and do not have this start\n"
            . "menu option, you should find and edit your httpd.conf file manually\n"
            . "(usually in a location like c:\\xampp\\apache\\conf).\n";
    }

    /**
     * Get instructions for editing the Apache configuration under Linux.
     *
     * @return string
     */
    protected function getLinuxApacheMessage()
    {
        if (is_dir('/etc/httpd/conf.d')) {                      // Mandriva / RedHat
            $confD = '/etc/httpd/conf.d';
            $httpdConf = '/etc/httpd/conf/httpd.conf';
        } elseif (is_dir('/etc/apache2/2.2/conf.d')) {         // Solaris
            $confD = '/etc/apache2/2.2/conf.d';
            $httpdConf = '/etc/apache2/2.2/httpd.conf';
        } elseif (is_dir('/etc/apache2/conf-enabled')) {   // new Ubuntu / OpenSUSE
            $confD = '/etc/apache2/conf-enabled';
            $httpdConf = '/etc/apache2/apache2.conf';
        } elseif (is_dir('/etc/apache2/conf.d')) {         // old Ubuntu / OpenSUSE
            $confD = '/etc/apache2/conf.d';
            $httpdConf = '/etc/apache2/httpd.conf';
        } elseif (is_dir('/opt/local/apache2/conf/extra')) {   // Mac with Mac Ports
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
        $symlink = basename($this->overrideDir);
        $symlink = ($symlink == 'local') ? 'vufind' : ('vufind-' . $symlink);
        $symlink .= '.conf';

        return "You can do it in either of two ways:\n\n"
            . "    a) Add this line to your {$httpdConf} file:\n"
            . "       Include {$this->overrideDir}/httpd-vufind.conf\n\n"
            . "    b) Link the configuration to Apache's config directory like this:"
            . "\n       ln -s {$this->overrideDir}/httpd-vufind.conf "
            . "{$confD}/{$symlink}\n"
            . "\nOption b is preferable if your platform supports it,\n"
            . "but option a is more certain to be supported.\n";
    }

    /**
     * Display system-specific information for where configuration files are found
     * and/or symbolic links should be created.
     *
     * @param OutputInterface $output Output object
     *
     * @return void
     */
    protected function getApacheLocation(OutputInterface $output)
    {
        // There is one special case for Windows, and a variety of different
        // Unix-flavored possibilities that all work similarly.
        $msg = PHP_OS_FAMILY === 'Windows'
            ? $this->getWindowsApacheMessage() : $this->getLinuxApacheMessage();
        $output->writeln($msg);
    }

    /**
     * Validate a base path. Returns true on success, message on failure.
     *
     * @param string $basePath   String to validate.
     * @param bool   $allowEmpty Are empty values acceptable?
     *
     * @return bool|string
     */
    protected function validateBasePath($basePath, $allowEmpty = false)
    {
        if ($allowEmpty && empty($basePath)) {
            return true;
        }
        return preg_match('/^\/[\w_-]*$/', $basePath)
            ? true
            : 'Error: Base path must start with a slash and contain only'
                . ' alphanumeric characters, dash or underscore.';
    }

    /**
     * Validate a Solr port number. Returns true on success, message on failure.
     *
     * @param string $solrPort Port to validate.
     *
     * @return bool|string
     */
    protected function validateSolrPort($solrPort)
    {
        if (is_numeric($solrPort)) {
            return true;
        }
        return 'Solr port must be a number.';
    }

    /**
     * Get a base path from the user (or return a default).
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return string
     */
    protected function getBasePath(InputInterface $input, OutputInterface $output)
    {
        // Get VuFind base path:
        while (true) {
            $basePathInput = $this->getInput(
                $input,
                $output,
                "What base path should be used in VuFind®'s URL? [{$this->basePath}] "
            );
            if (empty($basePathInput)) {
                return $this->basePath;
            } elseif (($result = $this->validateBasePath($basePathInput)) === true) {
                return $basePathInput;
            }
            $output->writeln($result);
        }
    }

    /**
     * Get a Solr port number from the user (or return a default).
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return string
     */
    protected function getSolrPort(InputInterface $input, OutputInterface $output)
    {
        // Get VuFind base path:
        while (true) {
            $solrInput = $this->getInput(
                $input,
                $output,
                "What port number should Solr use? [{$this->solrPort}] "
            );
            if (empty($solrInput)) {
                return $this->solrPort;
            } elseif (($result = $this->validateSolrPort($solrInput)) === true) {
                return $solrInput;
            }
            $output->writeln($result);
        }
    }

    /**
     * Initialize the override directory and report success or failure.
     *
     * @param string $dir Path to attempt to initialize
     *
     * @return bool|string
     */
    protected function initializeOverrideDir($dir)
    {
        return $this->buildDirs(
            [
                $dir,
                $dir . '/cache',
                $dir . '/config',
                $dir . '/harvest',
                $dir . '/import',
            ]
        );
    }

    /**
     * Get an override directory from the user (or return a default).
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return string
     */
    protected function getOverrideDir(InputInterface $input, OutputInterface $output)
    {
        // Get override directory path:
        while (true) {
            $overrideDirInput = $this->getInput(
                $input,
                $output,
                'Where would you like to store your local settings? '
                . "[{$this->overrideDir}] "
            );
            if (empty($overrideDirInput)) {
                return $this->overrideDir;
            } elseif (!$this->initializeOverrideDir($overrideDirInput)) {
                $output->writeln(
                    "Error: Cannot initialize settings in '$overrideDirInput'.\n"
                );
            }
            return str_replace('\\', '/', realpath($overrideDirInput));
        }
    }

    /**
     * Validate a comma-separated list of module names. Returns true on success,
     * message on failure.
     *
     * @param string $modules Module names to validate.
     *
     * @return bool|string
     */
    protected function validateModules($modules)
    {
        foreach (explode(',', $modules) as $module) {
            $result = $this->validateModule(trim($module));
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
    protected function validateModule($module)
    {
        $regex = '/^[a-zA-Z][0-9a-zA-Z_]*$/';
        $illegalModules = [
            'VuFind', 'VuFindAdmin', 'VuFindConsole', 'VuFindDevTools',
            'VuFindLocalTemplate', 'VuFindSearch', 'VuFindTest', 'VuFindTheme',
        ];
        if (in_array($module, $illegalModules)) {
            return "{$module} is a reserved module name; please try another.";
        } elseif (empty($module) || preg_match($regex, $module)) {
            return true;
        }
        return "Illegal name: {$module}; please use alphanumeric text.";
    }

    /**
     * Get the custom module name from the user (or blank for none).
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return string
     */
    protected function getModule(InputInterface $input, OutputInterface $output)
    {
        // Get custom module name:
        $output->writeln(
            "\nVuFind® supports use of a custom module for storing local code "
            . "changes.\nIf you do not plan to customize the code, you can "
            . "skip this step.\nIf you decide to use a custom module, the name "
            . "you choose will be used for\nthe module's directory name and its "
            . 'PHP namespace.'
        );
        while (true) {
            $moduleInput = trim(
                $this->getInput(
                    $input,
                    $output,
                    "\nWhat module name would you like to use? [blank for none] "
                )
            );
            if (($result = $this->validateModules($moduleInput)) === true) {
                return $moduleInput;
            }
            $output->writeln("\n$result");
        }
    }

    /**
     * Get the user's preferred multisite mode.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int
     */
    protected function getMultisiteMode(
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln(
            "\nWhen running multiple VuFind® sites against a single installation, you"
            . " need\nto decide how to distinguish between instances. Choose an "
            . "option:\n\n" . self::MULTISITE_DIR_BASED . '.) Directory-based '
            . "(i.e. http://server/vufind1 vs. http://server/vufind2)\n"
            . self::MULTISITE_HOST_BASED
            . '.) Host-based (i.e. http://vufind1.server vs. http://vufind2.server)'
            . "\n\nor enter " . self::MULTISITE_NONE . ' to disable multisite mode.'
        );
        $legal = [
            self::MULTISITE_NONE,
            self::MULTISITE_DIR_BASED,
            self::MULTISITE_HOST_BASED,
        ];
        while (true) {
            $response = $this->getInput(
                $input,
                $output,
                "\nWhich option do you want? "
            );
            if (is_numeric($response) && in_array(intval($response), $legal)) {
                return intval($response);
            }
            $output->writeln('Invalid selection.');
        }
    }

    /**
     * Validate the user's hostname input. Returns true on success, message on
     * failure.
     *
     * @param string $host String to check
     *
     * @return bool|string
     */
    protected function validateHost($host)
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
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return string
     */
    protected function getHost(InputInterface $input, OutputInterface $output)
    {
        while (true) {
            $response = $this->getInput(
                $input,
                $output,
                "\nPlease enter the hostname for your site: "
            );
            if (($result = $this->validateHost($response)) === true) {
                return $response;
            }
            $output->writeln($result);
        }
    }

    /**
     * Fetch a single line of input from the user.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     * @param string          $prompt Prompt to display to the user.
     *
     * @return string        User-entered response.
     */
    protected function getInput(
        InputInterface $input,
        OutputInterface $output,
        string $prompt
    ): string {
        $question = new Question($prompt, '');
        return $this->getHelper('question')->ask($input, $output, $question);
    }

    /**
     * Back up an existing file and inform the user. Return true on success,
     * error message otherwise.
     *
     * @param OutputInterface $output   Output object
     * @param string          $filename File to back up (if it exists)
     * @param string          $desc     Description of file (for output message)
     *
     * @return bool|string
     */
    protected function backUpFile(OutputInterface $output, string $filename, string $desc)
    {
        if ($this->makeBackups && file_exists($filename)) {
            $bak = $filename . '.bak.' . time();
            if (!copy($filename, $bak)) {
                return "Problem backing up $filename to $bak";
            }
            $output->writeln("Backed up existing $desc to $bak.");
        }
        return true;
    }

    /**
     * Generate the Apache configuration. Returns true on success, error message
     * otherwise.
     *
     * @param OutputInterface $output Output object
     *
     * @return bool|string
     */
    protected function buildApacheConfig(OutputInterface $output)
    {
        $baseConfig = $this->baseDir . '/config/vufind/httpd-vufind.conf';
        $config = @file_get_contents($baseConfig);
        if (empty($config)) {
            return "Problem reading {$baseConfig}.";
        }
        $config = str_replace('/usr/local/vufind/local', '%override-dir%', $config);
        $config = str_replace('/usr/local/vufind', '%base-dir%', $config);
        $config = preg_replace('|([^/])\/vufind|', '$1%base-path%', $config);
        $config = str_replace('%override-dir%', $this->overrideDir, $config);
        $config = str_replace('%base-dir%', $this->baseDir, $config);
        $config = str_replace('%base-path%', $this->basePath, $config);
        // Special cases for root basePath:
        if ('/' == $this->basePath) {
            $config = str_replace('//', '/', $config);
            $config = str_replace('Alias /', '#Alias /', $config);
        }
        if (!empty($this->module)) {
            $config = str_replace(
                '#SetEnv VUFIND_LOCAL_MODULES VuFindLocalTemplate',
                "SetEnv VUFIND_LOCAL_MODULES {$this->module}",
                $config
            );
        }

        // In multisite mode, we need to make environment variables conditional:
        switch ($this->multisiteMode) {
            case self::MULTISITE_DIR_BASED:
                $config = preg_replace(
                    '/SetEnv\s+(\w+)\s+(.*)/',
                    'SetEnvIf Request_URI "^' . $this->basePath . '" $1=$2',
                    $config
                );
                break;
            case self::MULTISITE_HOST_BASED:
                if (($result = $this->validateHost($this->host)) !== true) {
                    return $result;
                }
                $config = preg_replace(
                    '/SetEnv\s+(\w+)\s+(.*)/',
                    'SetEnvIfNoCase Host ' . str_replace('.', '\.', $this->host)
                    . ' $1=$2',
                    $config
                );
                break;
        }

        $target = $this->overrideDir . '/httpd-vufind.conf';
        if (($msg = $this->backUpFile($output, $target, 'Apache configuration')) !== true) {
            return $msg;
        }
        return $this->writeFileToDisk($target, $config)
            ? true : "Problem writing {$this->overrideDir}/httpd-vufind.conf.";
    }

    /**
     * Get an array of environment variables.
     *
     * @return array
     */
    protected function getEnvironmentVariables(): array
    {
        $vars = [
            'VUFIND_HOME' => $this->baseDir,
            'VUFIND_LOCAL_DIR' => $this->overrideDir,
            'VUFIND_LOCAL_MODULES' => $this->module,
            'SOLR_PORT' => $this->solrPort,
        ];
        if (empty($vars['VUFIND_LOCAL_MODULES'])) {
            unset($vars['VUFIND_LOCAL_MODULES']);
        }
        return $vars;
    }

    /**
     * Build the Unix-specific environment configuration. Returns true on success,
     * error message otherwise.
     *
     * @param OutputInterface $output Output object
     *
     * @return bool|string
     */
    protected function buildUnixEnvironment($output)
    {
        $filename = $this->baseDir . '/env.sh';
        if (($msg = $this->backUpFile($output, $filename, 'Unix environment file')) !== true) {
            return $msg;
        }
        $env = '';
        foreach ($this->getEnvironmentVariables() as $key => $val) {
            $env .= "export $key=$val\n";
        }
        return $this->writeFileToDisk($filename, $env)
            ? true : "Problem writing {$filename}.";
    }

    /**
     * Build the Windows-specific startup configuration. Returns true on success,
     * error message otherwise.
     *
     * @param OutputInterface $output Output object
     *
     * @return bool|string
     */
    protected function buildWindowsConfig($output)
    {
        $filename = $this->baseDir . '/env.bat';
        if (($msg = $this->backUpFile($output, $filename, 'Windows environment file')) !== true) {
            return $msg;
        }
        $batch = '';
        foreach ($this->getEnvironmentVariables() as $key => $val) {
            $batch .= "@set $key=$val\n";
        }
        return $this->writeFileToDisk($filename, $batch)
            ? true : "Problem writing {$filename}.";
    }

    /**
     * Configure a SolrMarc properties file. Returns true on success, error message
     * otherwise.
     *
     * @param OutputInterface $output   Output object
     * @param string          $filename The properties file to configure
     *
     * @return bool|string
     */
    protected function buildImportConfig(OutputInterface $output, $filename)
    {
        $target = $this->overrideDir . '/import/' . $filename;
        if (($msg = $this->backUpFile($output, $target, 'import configuration')) !== true) {
            return $msg;
        }
        $import = @file_get_contents($this->baseDir . '/import/' . $filename);
        $import = str_replace(
            ['/usr/local/vufind', ':8983'],
            [$this->baseDir, ':' . $this->solrPort],
            $import
        );
        $import = preg_replace(
            "/^\s*solrmarc.path\s*=.*$/m",
            "solrmarc.path = {$this->overrideDir}/import|{$this->baseDir}/import",
            $import
        );
        if (!$this->writeFileToDisk($target, $import)) {
            return "Problem writing {$this->overrideDir}/import/{$filename}.";
        }
        return true;
    }

    /**
     * Build a set of directories.
     *
     * @param array $dirs Directories to build
     *
     * @return bool|string True on success, name of problem directory on failure
     */
    protected function buildDirs($dirs)
    {
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !@mkdir($dir)) {
                return $dir;
            }
        }
        return true;
    }

    /**
     * Make sure all modules exist (and create them if they do not). Returns true
     * on success, error message otherwise.
     *
     * @return bool|string
     */
    protected function buildModules()
    {
        if (!empty($this->module)) {
            foreach (explode(',', $this->module) as $module) {
                $moduleDir = $this->baseDir . '/module/' . $module;
                // Is module missing? If so, create it from the template:
                if (!file_exists($moduleDir . '/Module.php')) {
                    if (($result = $this->buildModule($module)) !== true) {
                        return $result;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Build the module for storing local code changes. Returns true on success,
     * error message otherwise.
     *
     * @param string $module The name of the new module (assumed valid!)
     *
     * @return bool|string
     */
    protected function buildModule($module)
    {
        // Create directories:
        $moduleDir = $this->baseDir . '/module/' . $module;
        $dirStatus = $this->buildDirs(
            [
                $moduleDir,
                $moduleDir . '/config',
                $moduleDir . '/src',
                $moduleDir . '/src/' . $module,
            ]
        );
        if ($dirStatus !== true) {
            return "Problem creating {$dirStatus}.";
        }

        // Copy configuration:
        $configFile = $this->baseDir
            . '/module/VuFindLocalTemplate/config/module.config.php';
        $config = @file_get_contents($configFile);
        if (!$config) {
            return "Problem reading {$configFile}.";
        }
        $success = $this->writeFileToDisk(
            $moduleDir . '/config/module.config.php',
            str_replace('VuFindLocalTemplate', $module, $config)
        );
        if (!$success) {
            return "Problem writing {$moduleDir}/config/module.config.php.";
        }

        // Copy PHP code:
        $moduleFile = $this->baseDir . '/module/VuFindLocalTemplate/Module.php';
        $contents = @file_get_contents($moduleFile);
        if (!$contents) {
            return "Problem reading {$moduleFile}.";
        }
        $success = $this->writeFileToDisk(
            $moduleDir . '/Module.php',
            str_replace('VuFindLocalTemplate', $module, $contents)
        );
        return $success ? true : "Problem writing {$moduleDir}/Module.php.";
    }

    /**
     * Display an error message and return a failure status.
     *
     * @param OutputInterface $output Output object
     * @param string          $msg    Error message
     * @param int             $status Error status
     *
     * @return int
     */
    protected function failWithError(
        OutputInterface $output,
        string $msg,
        int $status = 1
    ): int {
        $output->writeln($msg);
        return $status;
    }

    /**
     * Display the final message after successful installation.
     *
     * @param OutputInterface $output Output object
     *
     * @return void
     */
    protected function displaySuccessMessage(OutputInterface $output)
    {
        if ($this->showApacheHelp) {
            $output->writeln(
                "Apache configuration written to {$this->overrideDir}/httpd-vufind.conf."
                . "\n\nYou now need to load this configuration into Apache."
            );
            $this->getApacheLocation($output);
            if (!empty($this->host)) {
                $output->writeln(
                    'Since you are using a host-based multisite configuration, you will '
                    . "also \nneed to do some virtual host configuration. See\n"
                    . "     http://httpd.apache.org/docs/2.4/vhosts/\n"
                );
            }
            if ('/' == $this->basePath) {
                $output->writeln(
                    'Since you are installing VuFind® at the root of your domain, you '
                    . "will also\nneed to edit your Apache configuration to change "
                    . "DocumentRoot to:\n" . $this->baseDir . "/public\n"
                );
            }
            $output->writeln(
                "Once the configuration is linked, restart Apache. You should now be able\n"
                . "to access VuFind® at http://localhost{$this->basePath}"
            );
        }
        $output->writeln('');
        $output->writeln("For proper use of command line tools, you should ensure that your\n");
        $finalMsg = empty($this->addOptionmodule)
            ? "VUFIND_HOME and VUFIND_LOCAL_DIR environment variables are set to\n"
            . "{$this->baseDir} and {$this->overrideDir} respectively."
            : "VUFIND_HOME, VUFIND_LOCAL_MODULES and VUFIND_LOCAL_DIR environment\n"
            . "variables are set to {$this->baseDir}, {$this->module} and "
            . "{$this->overrideDir} respectively.";
        $output->writeln($finalMsg);
        $output->writeln('');
    }

    /**
     * Collect input parameters, and return a status (0 = proceed, 1 = fail).
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function collectParameters(
        InputInterface $input,
        OutputInterface $output
    ) {
        // Are we allowing user interaction?
        $interactive = !$input->getOption('non-interactive');
        $userInputNeeded = [];

        // Load user settings if we are not forcing defaults:
        if (!$input->getOption('use-defaults')) {
            $overrideDir = trim($input->getOption('overridedir') ?? '');
            if (!empty($overrideDir)) {
                $this->overrideDir = $overrideDir;
            } elseif ($interactive) {
                $userInputNeeded['overrideDir'] = true;
            }
            $moduleName = trim($input->getOption('module-name') ?? '');
            if (!empty($moduleName) && $moduleName !== 'disabled') {
                if (($result = $this->validateModules($moduleName)) !== true) {
                    return $this->failWithError($output, $result);
                }
                $this->module = $moduleName;
            } elseif ($interactive) {
                $userInputNeeded['module'] = true;
            }

            $basePath = trim($input->getOption('basepath') ?? '');
            if (!empty($basePath)) {
                if (($result = $this->validateBasePath($basePath, true)) !== true) {
                    return $this->failWithError($output, $result);
                }
                $this->basePath = $basePath;
            } elseif ($interactive) {
                $userInputNeeded['basePath'] = true;
            }

            $solrPort = trim($input->getOption('solr-port') ?? '');
            if (!empty($solrPort)) {
                if (($result = $this->validateSolrPort($solrPort)) !== true) {
                    return $this->failWithError($output, $result);
                }
                $this->solrPort = $solrPort;
            } elseif ($interactive) {
                $userInputNeeded['solr-port'] = true;
            }

            // We assume "single site" mode unless the --multisite option is set;
            // note that $mode will be null if the user provided the option with
            // no value specified, and false if the user did not provide the option.
            $mode = $input->getOption('multisite');
            if ($mode === 'directory') {
                $this->multisiteMode = self::MULTISITE_DIR_BASED;
            } elseif ($mode === 'host') {
                $this->multisiteMode = self::MULTISITE_HOST_BASED;
            } elseif ($mode !== true && $mode !== null && $mode !== false) {
                return $this->failWithError(
                    $output,
                    'Unexpected multisite mode: ' . $mode
                );
            } elseif ($interactive && $mode !== false) {
                $userInputNeeded['multisiteMode'] = true;
            }

            // Now that we've validated as many parameters as possible, retrieve
            // user input where needed.
            if (isset($userInputNeeded['overrideDir'])) {
                $this->overrideDir = $this->getOverrideDir($input, $output);
            }
            if (isset($userInputNeeded['module'])) {
                $this->module = $this->getModule($input, $output);
            }
            if (isset($userInputNeeded['basePath'])) {
                $this->basePath = $this->getBasePath($input, $output);
            }
            if (isset($userInputNeeded['solr-port'])) {
                $this->solrPort = $this->getSolrPort($input, $output);
            }
            if (isset($userInputNeeded['multisiteMode'])) {
                $this->multisiteMode = $this->getMultisiteMode($input, $output);
            }

            // Load supplemental multisite parameters:
            if ($this->multisiteMode == self::MULTISITE_HOST_BASED) {
                $hostOption = trim($input->getOption('hostname'));
                $this->host = (!empty($hostOption) || !$interactive)
                    ? $hostOption : $this->getHost($input, $output);
            }
        }

        // Normalize the module setting to remove whitespace:
        $this->module = preg_replace('/\s/', '', $this->module);

        // Should we make backups of existing files?
        if ($input->getOption('skip-backups')) {
            $this->makeBackups = false;
        }

        // Should we display Apache help messages?
        $this->showApacheHelp = !$input->getOption('no-apache-help');
        return 0;
    }

    /**
     * Process collected parameters, and return a status (0 = proceed, 1 = fail).
     *
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function processParameters(OutputInterface $output)
    {
        // Make sure the override directory is initialized (using defaults or CLI
        // parameters will not have initialized it yet; attempt to reinitialize it
        // here is harmless if it was already initialized in interactive mode):
        if (!$this->initializeOverrideDir($this->overrideDir)) {
            return $this->failWithError(
                $output,
                "Cannot initialize local override directory: {$this->overrideDir}"
            );
        }

        // Build the Windows start file in case we need it:
        if (($result = $this->buildWindowsConfig($output)) !== true) {
            return $this->failWithError($output, $result);
        }

        // Build a Unix environment file in case we need it:
        if (($result = $this->buildUnixEnvironment($output)) !== true) {
            return $this->failWithError($output, $result);
        }

        // Build the import configuration:
        foreach (['import.properties', 'import_auth.properties'] as $file) {
            if (($result = $this->buildImportConfig($output, $file)) !== true) {
                return $this->failWithError($output, $result);
            }
        }

        // Build the custom module(s), if necessary:
        if (($result = $this->buildModules()) !== true) {
            return $this->failWithError($output, $result);
        }

        // Build the final configuration:
        if (($result = $this->buildApacheConfig($output)) !== true) {
            return $this->failWithError($output, $result);
        }
        return 0;
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("VuFind® has been found in {$this->baseDir}.");

        // Collect and process parameters, and stop if an error is encountered
        // along the way....
        if (
            $this->collectParameters($input, $output) !== 0
            || $this->processParameters($output) !== 0
        ) {
            return 1;
        }

        // Report success:
        $this->displaySuccessMessage($output);
        return 0;
    }
}
