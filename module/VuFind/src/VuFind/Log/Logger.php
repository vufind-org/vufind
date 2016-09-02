<?php
/**
 * VuFind Logger
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Log;
use Zend\Log\Logger as BaseLogger,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * This class wraps the BaseLogger class to allow for log verbosity
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Logger extends BaseLogger implements ServiceLocatorAwareInterface
{
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;

    /**
     * Is debug logging enabled?
     *
     * @var bool
     */
    protected $debugNeeded = false;

    /**
     * Set configuration
     *
     * @param \Zend\Config\Config $config VuFind configuration
     *
     * @return void
     */
    public function setConfig($config)
    {
        // DEBUGGER
        if (!$config->System->debug == false) {
            $this->addDebugWriter($config->System->debug);
        }

        // Activate database logging, if applicable:
        if (isset($config->Logging->database)) {
            $parts = explode(':', $config->Logging->database);
            $table_name = $parts[0];
            $error_types = isset($parts[1]) ? $parts[1] : '';

            $columnMapping = [
                'priority' => 'priority',
                'message' => 'message',
                'logtime' => 'timestamp',
                'ident' => 'ident'
            ];

            // Make Writers
            $filters = explode(',', $error_types);
            $writer = new Writer\Db(
                $this->getServiceLocator()->get('VuFind\DbAdapter'),
                $table_name, $columnMapping
            );
            $this->addWriters($writer, $filters);
        }

        // Activate file logging, if applicable:
        if (isset($config->Logging->file)) {
            $parts = explode(':', $config->Logging->file);
            $file = $parts[0];
            $error_types = isset($parts[1]) ? $parts[1] : '';

            // Make Writers
            $filters = explode(',', $error_types);
            $writer = new Writer\Stream($file);
            $this->addWriters($writer, $filters);
        }

        // Activate email logging, if applicable:
        if (isset($config->Logging->email)) {
            // Set up the logger's mailer to behave consistently with VuFind's
            // general mailer:
            $parts = explode(':', $config->Logging->email);
            $email = $parts[0];
            $error_types = isset($parts[1]) ? $parts[1] : '';

            // use smtp
            $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
            $msg = $mailer->getNewMessage()
                ->addFrom($config->Site->email)
                ->addTo($email)
                ->setSubject('VuFind Log Message');

            // Make Writers
            $filters = explode(',', $error_types);
            $writer = new Writer\Mail($msg, $mailer->getTransport());
            $this->addWriters($writer, $filters);
        }

        // Activate slack logging, if applicable:
        if (isset($config->Logging->slack) && isset($config->Logging->slackurl)) {
            $options = [];
            // Get config
            list($channel, $error_types) = explode(':', $config->Logging->slack);
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
                $this->getServiceLocator()->get('VuFind\Http')->createClient(),
                $options
            );
            $writer->setContentType('application/json');
            $formatter = new \Zend\Log\Formatter\Simple(
                "*%priorityName%*: %message%"
            );
            $writer->setFormatter($formatter);
            $this->addWriters($writer, $filters);
        }

        // Null (no-op) writer to avoid errors
        if (count($this->writers) == 0) {
            $nullWriter = 'Zend\Log\Writer\Noop';
            $this->addWriter(new $nullWriter());
        }
    }

    /**
     * Add the standard debug stream writer.
     *
     * @param bool|int $debug Debug mode/level
     *
     * @return void
     */
    public function addDebugWriter($debug)
    {
        // Only add debug writer ONCE!
        static $hasDebugWriter = false;
        if ($hasDebugWriter) {
            return;
        }

        $hasDebugWriter = true;
        $writer = new Writer\Stream('php://output');
        $formatter = new \Zend\Log\Formatter\Simple(
            '<pre>%timestamp% %priorityName%: %message%</pre>' . PHP_EOL
        );
        $writer->setFormatter($formatter);
        $this->addWriters($writer, 'debug-' . (is_int($debug) ? $debug : '5'));
    }

    /**
     * Applies an array of filters to a writer
     *
     * Filter keys: alert, error, notice, debug
     *
     * @param Zend\Log\Writer\WriterInterface $writer  The writer to apply the
     * filters to
     * @param string|array                    $filters An array or comma-separated
     * string of
     * logging levels
     *
     * @return void
     */
    protected function addWriters($writer, $filters)
    {
        if (!is_array($filters)) {
            $filters = explode(',', $filters);
        }

        foreach ($filters as $filter) {
            $parts = explode('-', $filter);
            $priority = $parts[0];
            $verbosity = isset($parts[1]) ? $parts[1] : false;

            // VuFind's configuration provides four priority options, each
            // combining two of the standard Zend levels.
            switch(trim($priority)) {
            case 'debug':
                // Set static flag indicating that debug is turned on:
                $this->debugNeeded = true;

                $max = BaseLogger::INFO;  // Informational: informational messages
                $min = BaseLogger::DEBUG; // Debug: debug messages
                break;
            case 'notice':
                $max = BaseLogger::WARN;  // Warning: warning conditions
                $min = BaseLogger::NOTICE;// Notice: normal but significant condition
                break;
            case 'error':
                $max = BaseLogger::CRIT;  // Critical: critical conditions
                $min = BaseLogger::ERR;   // Error: error conditions
                break;
            case 'alert':
                $max = BaseLogger::EMERG; // Emergency: system is unusable
                $min = BaseLogger::ALERT; // Alert: action must be taken immediately
                break;
            default:                    // INVALID FILTER
                continue;
            }

            // Clone the submitted writer since we'll need a separate instance of the
            // writer for each selected priority level.
            $newWriter = clone($writer);

            // verbosity
            if ($verbosity) {
                if (method_exists($newWriter, 'setVerbosity')) {
                    $newWriter->setVerbosity($verbosity);
                } else {
                    throw new \Exception(
                        get_class($newWriter) . ' does not support verbosity.'
                    );
                }
            }

            // filtering -- only log messages between the min and max priority levels
            $filter1 = new \Zend\Log\Filter\Priority($min, '<=');
            $filter2 = new \Zend\Log\Filter\Priority($max, '>=');
            $newWriter->addFilter($filter1);
            $newWriter->addFilter($filter2);

            // add the writer
            $this->addWriter($newWriter);
        }
    }

    /**
     * Is one of the log writers listening for debug messages?  (This is useful to
     * know, since some code can save time that would be otherwise wasted generating
     * debug messages if we know that no one is listening).
     *
     * @return bool
     */
    public function debugNeeded()
    {
        return $this->debugNeeded;
    }

    /**
     * Add a message as a log entry
     *
     * @param int               $priority Priority
     * @param mixed             $message  Message
     * @param array|Traversable $extra    Extras
     *
     * @return Logger
     */
    public function log($priority, $message, $extra = [])
    {
        // Special case to handle arrays of messages (for multi-verbosity-level
        // logging, not supported by base class):
        if (is_array($message)) {
            $timestamp = new \DateTime();
            foreach ($this->writers->toArray() as $writer) {
                $writer->write(
                    [
                        'timestamp'    => $timestamp,
                        'priority'     => (int) $priority,
                        'priorityName' => $this->priorities[$priority],
                        'message'      => $message,
                        'extra'        => $extra
                    ]
                );
            }
            return $this;
        }
        return parent::log($priority, $message, $extra);
    }

    /**
     * Given an exception, return a severity level for logging purposes.
     *
     * @param \Exception $error Exception to analyze
     *
     * @return int
     */
    protected function getSeverityFromException($error)
    {
        // Treat unexpected or 5xx errors as more severe than 4xx errors.
        if ($error instanceof \VuFind\Exception\HttpStatusInterface
            && in_array($error->getHttpStatus(), [403, 404])
        ) {
            return BaseLogger::WARN;
        }
        return BaseLogger::CRIT;
    }

    /**
     * Log an exception triggered by ZF2 for administrative purposes.
     *
     * @param \Exception              $error  Exception to log
     * @param \Zend\Stdlib\Parameters $server Server metadata
     *
     * @return void
     */
    public function logException($error, $server)
    {
        // We need to build a variety of pieces so we can supply
        // information at five different verbosity levels:
        $baseError = get_class($error) . ' : ' . $error->getMessage();
        $prev = $error->getPrevious();
        while ($prev) {
            $baseError .= ' ; ' . get_class($prev) . ' : ' . $prev->getMessage();
            $prev = $prev->getPrevious();
        }
        $referer = $server->get('HTTP_REFERER', 'none');
        $basicServer
            = '(Server: IP = ' . $server->get('REMOTE_ADDR') . ', '
            . 'Referer = ' . $referer . ', '
            . 'User Agent = '
            . $server->get('HTTP_USER_AGENT') . ', '
            . 'Host = '
            . $server->get('HTTP_HOST') . ', '
            . 'Request URI = '
            . $server->get('REQUEST_URI') . ')';
        $detailedServer = "\nServer Context:\n"
            . print_r($server->toArray(), true);
        $basicBacktrace = $detailedBacktrace = "\nBacktrace:\n";
        if (is_array($error->getTrace())) {
            foreach ($error->getTrace() as $line) {
                if (!isset($line['file'])) {
                    $line['file'] = 'unlisted file';
                }
                if (!isset($line['line'])) {
                    $line['line'] = 'unlisted';
                }
                $basicBacktraceLine = $detailedBacktraceLine = $line['file'] .
                    ' line ' . $line['line'] . ' - ' .
                    (isset($line['class']) ? 'class = ' . $line['class'] . ', ' : '')
                    . 'function = ' . $line['function'];
                $basicBacktrace .= "{$basicBacktraceLine}\n";
                if (!empty($line['args'])) {
                    $args = [];
                    foreach ($line['args'] as $i => $arg) {
                        $args[] = $i . ' = ' . $this->argumentToString($arg);
                    }
                    $detailedBacktraceLine .= ', args: ' . implode(', ', $args);
                } else {
                    $detailedBacktraceLine .= ', args: none.';
                }
                $detailedBacktrace .= "{$detailedBacktraceLine}\n";
            }
        }

        $errorDetails = [
            1 => $baseError,
            2 => $baseError . $basicServer,
            3 => $baseError . $basicServer . $basicBacktrace,
            4 => $baseError . $detailedServer . $basicBacktrace,
            5 => $baseError . $detailedServer . $detailedBacktrace
        ];

        $this->log($this->getSeverityFromException($error), $errorDetails);
    }

    /**
     * Convert function argument to a loggable string
     *
     * @param mixed $arg Argument
     *
     * @return string
     */
    protected function argumentToString($arg)
    {
        if (is_object($arg)) {
            return get_class($arg) . ' Object';
        }
        if (is_array($arg)) {
            $args = [];
            foreach ($arg as $key => $item) {
                $args[] = "$key => " . $this->argumentToString($item);
            }
            return 'array(' . implode(', ', $args) . ')';
        }
        if (is_bool($arg)) {
            return $arg ? 'true' : 'false';
        }
        if (is_int($arg) || is_float($arg)) {
            return (string)$arg;
        }
        if (is_null($arg)) {
            return 'null';
        }
        return "'$arg'";
    }
}
