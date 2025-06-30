<?php

/**
 * Factory for instantiating Logger
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Log;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\FilterHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Handler\HandlerInterface;
use Psr\Log\LogLevel;


use VuFind\Log\Handler\StreamHandler;
use VuFind\Log\Handler\MailHandler;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use VuFind\Config\Config;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Config\PluginManager as ConfigPluginManager;
use VuFind\Auth\Manager as AuthManager;
use LmcRbacMvc\Service\AuthorizationService;
use VuFind\Net\UserIpReader;
use VuFind\Mailer\Mailer;

use function count;
use function is_array;
use function is_int;
use function explode;
use function strrpos;
use function substr;
use function trim;
use function method_exists;
use function error_log;

/**
 * Factory for instantiating Logger
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class LoggerFactory implements FactoryInterface
{
    use EmailSettingsTrait;

    /**
     * Get a standard LineFormatter for file logging.
     *
     * @return LineFormatter
     */
    protected function getStandardFileFormatter(): LineFormatter
    {
        return new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s",
            true,
            true
        );
    }

    /**
     * Configure File handler.
     *
     * @param MonologLogger $monologLogger The Monolog logger instance to add handlers to.
     * @param string        $configString  The file configuration string (e.g., "path/to/file.log:error-1,debug-5").
     *
     * @return void
     */
    protected function addFileHandler(MonologLogger $monologLogger, string $configString): void
    {
        // Make sure to use only the last ':' after second character to avoid trouble
        // with Windows drive letters (e.g. "c:\something\logfile:error-5")
        $pos = strrpos($configString, ':', 2);
        if ($pos > 0) {
            $file = substr($configString, 0, $pos);
            $error_types = substr($configString, $pos + 1);
        } else {
            $file = $configString;
            $error_types = '';
        }
        

        $baseFileHandler = new StreamHandler($file, LogLevel::DEBUG, false);
        $baseFileHandler->setFormatter($this->getStandardFileFormatter());

        // Use the generic addHandlers method to configure and add the filtered handlers
        $this->addHandlers($monologLogger, $baseFileHandler, $error_types);
    }

    /**
     * Configure File handler.
     *
     * @param MonologLogger $monologLogger The Monolog logger instance to add handlers to.
     * @param string        $configString  The file configuration string (e.g., "path/to/file.log:error-1,debug-5").
     *
     * @return void
     */
    protected function addMailHandler(MonologLogger $monologLogger, Config $config, ContainerInterface $container): void
    {
        $parts = explode(':', $config->Logging->email);
        $email = $parts[0];
        $error_types = $parts[1] ?? '';

        $mailHandler = new MailHandler(
            $email, 
            'VuFind Log Message', 
            $this->getEmailSenderAddress($config),
            $container->get(Mailer::class)
        );
            
        $this->addHandlers($monologLogger, $mailHandler, $error_types);
    }

    /**
     * Is dynamic debug mode enabled?
     *
     * @param ContainerInterface $container Service manager
     *
     * @return bool
     */
    protected function hasDynamicDebug(ContainerInterface $container): bool
    {
        // Query parameters do not apply in console mode; if we do have a debug
        // query parameter, and the appropriate permission is set, activate dynamic
        // debug:
        if (
            PHP_SAPI !== 'cli'
            && $container->get('Request')->getQuery()->get('debug')
        ) {
            try {
                return $container->get(AuthorizationService::class)->isGranted('access.DebugMode');
            } catch (ServiceNotFoundException | ServiceNotCreatedException $e) {
                error_log("VuFind Log: Could not get AuthorizationService for dynamic debug: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    /**
     * Set configuration for the Monolog logger.
     * This method orchestrates the setup of all logging components.
     *
     * @param ContainerInterface $container Service manager
     * @param Logger             $vufindLogger The VuFind\Log\Logger (adapter) instance to configure.
     *
     * @return void
     */
    protected function configureMonologLogger(ContainerInterface $container, Logger $vufindLogger): void
    {
        $configManager = $container->get(ConfigPluginManager::class);
        $config = $configManager->get('config');
        $monologLogger = $vufindLogger->getMonologInstance(); // Get the actual Monolog instance

        // Add specific handlers based on config:
        // DEBUGGER
        if (!$config->System->debug == false || $this->hasDynamicDebug($container)) {
            $this->addDebugHandler($monologLogger, $config->System->debug);
        }

        // Activate file logging, if applicable:
        if (isset($config->Logging->file)) {
            $this->addFileHandler($monologLogger, $config->Logging->file);
        }

        // Activate email logging, if applicable:
        if (isset($config->Logging->email)) {
            $this->addMailHandler($monologLogger, $config, $container);
        }

        // Add common processors:
        $this->addCommonProcessors($monologLogger, $config, $container);
    }

    /**
     * Add the standard debug stream handler (output to browser/CLI).
     *
     * @param MonologLogger      $monologLogger The Monolog logger instance
     * @param bool|int $debug  Debug mode/level
     *
     * @return void
     */
    protected function addDebugHandler(MonologLogger $monologLogger, $debug): void {
        // Only add debug writer ONCE!
        static $hasDebugWriter = false;
        if ($hasDebugWriter) {
            return;
        }

        $hasDebugWriter = true;
        $debugFormatter = new LineFormatter(
            PHP_SAPI === 'cli'
                ? "[%datetime%] %level_name%: %message% %context% %extra%\n"
                : '<pre>[%datetime%] %level_name%: %message% %context% %extra%</pre>' . PHP_EOL,
            "Y-m-d H:i:s",
            true,
            true
        );
        $debugHandler = new StreamHandler('php://output');
        $debugHandler->setFormatter($debugFormatter);
        $level = (is_int($debug) ? $debug : '5');
        $this->addHandlers($monologLogger, $debugHandler, "debug-$level,notice-$level,error-$level,alert-$level");
    }

    /**
     * Add common Monolog processors to the logger.
     *
     * @param MonologLogger      $monologLogger The Monolog logger instance
     * @param Config             $config        VuFind configuration
     * @param ContainerInterface $container     Service manager
     *
     * @return void
     */
    protected function addCommonProcessors(
        MonologLogger $monologLogger,
        Config $config,
        ContainerInterface $container
    ): void {
        $monologLogger->pushProcessor(new PsrLogMessageProcessor());
        $logConfig = $config->Logging;
        if ($referenceId = $logConfig->reference_id ?? false) {
            if ('username' === $referenceId) {
                try {
                    $authManager = $container->get(AuthManager::class);
                    if ($user = $authManager->getUserObject()) {
                        $monologLogger->pushProcessor(function (array $record) use ($user) {
                            $record['extra']['username'] = $user->username;
                            return $record;
                        });
                    }
                } catch (ServiceNotFoundException | ServiceNotCreatedException $e) {
                    error_log("VuFind Log: Could not get AuthManager for ReferenceId processor: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Applies an array of filters to a writer
     *
     * Filter keys: alert, error, notice, debug
     *
     * @param MonologLogger             $monologLogger The Monolog logger instance to add handlers to.
     * @param HandlerInterface $baseHandler   The base Monolog handler to clone and filter
     * (e.g., StreamHandler).
     * @param string|array              $filters     An array or comma-separated string of
     * logging levels
     *
     * @throws \Exception If the base handler does not support verbosity when specified.
     * @return void
     */
    protected function addHandlers(
        MonologLogger $monologLogger,
        HandlerInterface $baseHandler,
        $filters
    ): void {
        if (!is_array($filters)) {
            $filters = explode(',', $filters);
        }

        foreach ($filters as $filter) {
            $parts = explode('-', $filter);
            $priority = $parts[0];
            // Ensure verbosity is an int, default to 1 if not specified or invalid
            $verbosity = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 1;

            $min = LogLevel::DEBUG; // Default min, will be overwritten by switch
            $max = LogLevel::EMERGENCY; // Default max, will be overwritten by switch

            // VuFind's configuration provides four priority options, each
            // combining two of the standard Monolog levels.
            switch (trim($priority)) {
                case 'debug':
                    $min = LogLevel::DEBUG;
                    $max = LogLevel::INFO;
                    break;
                case 'notice':
                    $min = LogLevel::NOTICE;
                    $max = LogLevel::WARNING;
                    break;
                case 'error':
                    $min = LogLevel::ERROR;
                    $max = LogLevel::CRITICAL;
                    break;
                case 'alert':
                    $min = LogLevel::ALERT;
                    $max = LogLevel::EMERGENCY;
                    break;
                default:
                    // INVALID FILTER, so skip it.
                    continue 2;
            }

            // Clone the submitted baseHandler since we'll need a separate instance
            // for each selected priority level, allowing distinct verbosity settings.
            $newHandler = clone $baseHandler;

            // Apply verbosity if specified and supported by the handler.
            // Check verbosity > 0, as 0 might mean no extra verbosity and 1 is default
            if ($verbosity > 0) {
                if (method_exists($newHandler, 'setVerbosity')) {
                    $newHandler->setVerbosity($verbosity);
                } else {
                    throw new \Exception(
                        $newHandler::class . ' does not support verbosity when a verbosity level is configured.'
                    );
                }
            }
            $filterHandler = new FilterHandler($newHandler, $min, $max);
            // Add the fully configured handler (wrapped in its filter) to the Monolog logger.
            $monologLogger->pushHandler($filterHandler);
        }
    }

    /**
     * Get proxy class to instantiate from the requested class name
     *
     * @param string $requestedName Service being created
     *
     * @return string
     */
    protected function getProxyClassName(string $requestedName): string
    {
        $className = $requestedName . 'Proxy';
        // Fall back to default if the class doesn't exist:
        if (!class_exists($className)) {
            return LoggerProxy::class;
        }
        return $className;
    }

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        // Construct the logger as a lazy loading proxy so that the object is not
        // instantiated until it is called. This helps break potential circular
        // dependencies with other services.
        $callback = function (&$wrapped, $proxy) use ($container, $requestedName) {
            // Now build the actual service:
            $wrapped = new $requestedName(
                $container->get(UserIpReader::class) // Use imported alias
            );
            $this->configureMonologLogger($container, $wrapped); // Call the Monolog configuration method
        };

        $proxyClass = $this->getProxyClassName($requestedName);
        return new $proxyClass($callback);
    }
}