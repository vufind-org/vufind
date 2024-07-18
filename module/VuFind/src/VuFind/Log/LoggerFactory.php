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

use Laminas\Config\Config;
use Laminas\Log\Writer\WriterInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function count;
use function is_array;
use function is_int;

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
    /**
     * Configure database writers.
     *
     * @param Logger             $logger    Logger object
     * @param ContainerInterface $container Service manager
     * @param string             $config    Configuration
     *
     * @return void
     */
    protected function addDbWriters(
        Logger $logger,
        ContainerInterface $container,
        $config
    ) {
        $parts = explode(':', $config);
        $table_name = $parts[0];
        $error_types = $parts[1] ?? '';

        $columnMapping = [
            'priority' => 'priority',
            'message' => 'message',
            'logtime' => 'timestamp',
            'ident' => 'ident',
        ];

        // Make Writers
        $filters = explode(',', $error_types);
        $writer = new Writer\Db(
            $container->get(\Laminas\Db\Adapter\Adapter::class),
            $table_name,
            $columnMapping
        );
        $this->addWriters($logger, $writer, $filters);
    }

    /**
     * Configure email writers.
     *
     * @param Logger             $logger    Logger object
     * @param ContainerInterface $container Service manager
     * @param Config             $config    Configuration
     *
     * @return void
     */
    protected function addEmailWriters(
        Logger $logger,
        ContainerInterface $container,
        Config $config
    ) {
        // Set up the logger's mailer to behave consistently with VuFind's
        // general mailer:
        $parts = explode(':', $config->Logging->email);
        $email = $parts[0];
        $error_types = $parts[1] ?? '';

        // use smtp
        $mailer = $container->get(\VuFind\Mailer\Mailer::class);
        $msg = $mailer->getNewMessage()
            ->addFrom($config->Site->email)
            ->addTo($email)
            ->setSubject('VuFind Log Message');

        // Make Writers
        $filters = explode(',', $error_types);
        $writer = new Writer\Mail($msg, $mailer->getTransport());
        $this->addWriters($logger, $writer, $filters);
    }

    /**
     * Configure File writers.
     *
     * @param Logger $logger Logger object
     * @param string $config Configuration
     *
     * @return void
     */
    protected function addFileWriters(Logger $logger, $config)
    {
        // Make sure to use only the last ':' after second character to avoid trouble
        // with Windows drive letters (e.g. "c:\something\logfile:error-5")
        $pos = strrpos($config, ':', 2);
        if ($pos > 0) {
            $file = substr($config, 0, $pos);
            $error_types = substr($config, $pos + 1);
        } else {
            $file = $config;
            $error_types = '';
        }

        // Make Writers
        $filters = explode(',', $error_types);
        $writer = new Writer\Stream($file);
        $this->addWriters($logger, $writer, $filters);
    }

    /**
     * Configure Slack writers.
     *
     * @param Logger             $logger    Logger object
     * @param ContainerInterface $container Service manager
     * @param Config             $config    Configuration
     *
     * @return void
     */
    protected function addSlackWriters(
        Logger $logger,
        ContainerInterface $container,
        Config $config
    ) {
        $options = [];
        // Get config
        [$channel, $error_types] = explode(':', $config->Logging->slack);
        if ($error_types == null) {
            $error_types = $channel;
            $channel = null;
        }
        if ($channel) {
            $options['channel'] = $channel;
        }
        if (isset($config->Logging->slackname)) {
            $options['name'] = $config->Logging->slackname;
        }
        $filters = explode(',', $error_types);
        // Make Writers
        $writer = new Writer\Slack(
            $config->Logging->slackurl,
            $container->get(\VuFindHttp\HttpService::class)->createClient(),
            $options
        );
        $writer->setContentType('application/json');
        $formatter = new \Laminas\Log\Formatter\Simple(
            '*%priorityName%*: %message%'
        );
        $writer->setFormatter($formatter);
        $this->addWriters($logger, $writer, $filters);
    }

    /**
     * Configure Office365 writers.
     *
     * @param Logger             $logger    Logger object
     * @param ContainerInterface $container Service manager
     * @param Config             $config    Configuration
     *
     * @return void
     */
    protected function addOffice365Writers(
        Logger $logger,
        ContainerInterface $container,
        Config $config
    ) {
        $options = [];
        // Get config
        $error_types = $config->Logging->office365;
        if (isset($config->Logging->office365_title)) {
            $options['title'] = $config->Logging->office365_title;
        }
        $filters = explode(',', $error_types);
        // Make Writers
        $writer = new Writer\Office365(
            $config->Logging->office365_url,
            $container->get(\VuFindHttp\HttpService::class)->createClient(),
            $options
        );
        $writer->setContentType('application/json');
        $formatter = new \Laminas\Log\Formatter\Simple(
            '*%priorityName%*: %message%'
        );
        $writer->setFormatter($formatter);
        $this->addWriters($logger, $writer, $filters);
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
            return $container->get(\LmcRbacMvc\Service\AuthorizationService::class)
                ->isGranted('access.DebugMode');
        }
        return false;
    }

    /**
     * Set configuration
     *
     * @param ContainerInterface $container Service manager
     * @param Logger             $logger    Logger to configure
     *
     * @return void
     */
    protected function configureLogger(ContainerInterface $container, Logger $logger)
    {
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        // Add a no-op writer so fatal errors are not triggered if log messages are
        // sent during the initialization process.
        $noOpWriter = new \Laminas\Log\Writer\Noop();
        $logger->addWriter($noOpWriter);

        // DEBUGGER
        if (!$config->System->debug == false || $this->hasDynamicDebug($container)) {
            $this->addDebugWriter($logger, $config->System->debug);
        }

        // Activate database logging, if applicable:
        if (isset($config->Logging->database)) {
            $this->addDbWriters($logger, $container, $config->Logging->database);
        }

        // Activate file logging, if applicable:
        if (isset($config->Logging->file)) {
            $this->addFileWriters($logger, $config->Logging->file);
        }

        // Activate email logging, if applicable:
        if (isset($config->Logging->email)) {
            $this->addEmailWriters($logger, $container, $config);
        }

        // Activate Office365 logging, if applicable:
        if (
            isset($config->Logging->office365)
            && isset($config->Logging->office365_url)
        ) {
            $this->addOffice365Writers($logger, $container, $config);
        }

        // Activate slack logging, if applicable:
        if (isset($config->Logging->slack) && isset($config->Logging->slackurl)) {
            $this->addSlackWriters($logger, $container, $config);
        }

        // We're done now -- clean out the no-op writer if any other writers
        // are found.
        if (count($logger->getWriters()) > 1) {
            $logger->removeWriter($noOpWriter);
        }

        // Add ReferenceId processor, if applicable:
        if ($referenceId = $config->Logging->reference_id ?? false) {
            if ('username' === $referenceId) {
                $authManager = $container->get(\VuFind\Auth\Manager::class);
                if ($user = $authManager->getUserObject()) {
                    $processor = new \Laminas\Log\Processor\ReferenceId();
                    $processor->setReferenceId($user->username);
                    $logger->addProcessor($processor);
                }
            }
        }
    }

    /**
     * Add the standard debug stream writer.
     *
     * @param Logger   $logger Logger object
     * @param bool|int $debug  Debug mode/level
     *
     * @return void
     */
    protected function addDebugWriter(Logger $logger, $debug)
    {
        // Only add debug writer ONCE!
        static $hasDebugWriter = false;
        if ($hasDebugWriter) {
            return;
        }

        $hasDebugWriter = true;
        $writer = new Writer\Stream('php://output');
        $formatter = new \Laminas\Log\Formatter\Simple(
            PHP_SAPI === 'cli'
                ? '%timestamp% %priorityName%: %message%'
                : '<pre>%timestamp% %priorityName%: %message%</pre>' . PHP_EOL
        );
        $writer->setFormatter($formatter);
        $level = (is_int($debug) ? $debug : '5');
        $this->addWriters(
            $logger,
            $writer,
            "debug-$level,notice-$level,error-$level,alert-$level"
        );
    }

    /**
     * Applies an array of filters to a writer
     *
     * Filter keys: alert, error, notice, debug
     *
     * @param Logger          $logger  Logger object
     * @param WriterInterface $writer  The writer to apply the
     * filters to
     * @param string|array    $filters An array or comma-separated
     * string of
     * logging levels
     *
     * @return void
     */
    protected function addWriters(Logger $logger, WriterInterface $writer, $filters)
    {
        if (!is_array($filters)) {
            $filters = explode(',', $filters);
        }

        foreach ($filters as $filter) {
            $parts = explode('-', $filter);
            $priority = $parts[0];
            $verbosity = $parts[1] ?? false;

            // VuFind's configuration provides four priority options, each
            // combining two of the standard Laminas levels.
            switch (trim($priority)) {
                case 'debug':
                    // Set static flag indicating that debug is turned on:
                    $logger->debugNeeded(true);

                    $max = Logger::INFO;  // Informational: informational messages
                    $min = Logger::DEBUG; // Debug: debug messages
                    break;
                case 'notice':
                    $max = Logger::WARN;  // Warning: warning conditions
                    $min = Logger::NOTICE;// Notice: normal but significant condition
                    break;
                case 'error':
                    $max = Logger::CRIT;  // Critical: critical conditions
                    $min = Logger::ERR;   // Error: error conditions
                    break;
                case 'alert':
                    $max = Logger::EMERG; // Emergency: system is unusable
                    $min = Logger::ALERT; // Alert: action must be taken immediately
                    break;
                default:
                    // INVALID FILTER, so skip it. We must continue 2 levels, so we
                    // continue the foreach loop instead of just breaking the switch.
                    continue 2;
            }

            // Clone the submitted writer since we'll need a separate instance of the
            // writer for each selected priority level.
            $newWriter = clone $writer;

            // verbosity
            if ($verbosity) {
                if (method_exists($newWriter, 'setVerbosity')) {
                    $newWriter->setVerbosity($verbosity);
                } else {
                    throw new \Exception(
                        $newWriter::class . ' does not support verbosity.'
                    );
                }
            }

            // filtering -- only log messages between the min and max priority levels
            $filter1 = new \Laminas\Log\Filter\Priority($min, '<=');
            $filter2 = new \Laminas\Log\Filter\Priority($max, '>=');
            $newWriter->addFilter($filter1);
            $newWriter->addFilter($filter2);

            // add the writer
            $logger->addWriter($newWriter);
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
        array $options = null
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
                $container->get(\VuFind\Net\UserIpReader::class)
            );
            $this->configureLogger($container, $wrapped);
        };

        $proxyClass = $this->getProxyClassName($requestedName);
        return new $proxyClass($callback);
    }
}
