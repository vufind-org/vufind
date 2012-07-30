<?php
/**
 * VF_Logger
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Log;
use VuFind\Config\Reader as ConfigReader, VuFind\Mailer,
    Zend\Db\TableGateway\Feature\GlobalAdapterFeature as DbGlobalAdapter,
    Zend\Log\Logger as BaseLogger;

/**
 * This class wraps the BaseLogger class to allow for log verbosity
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Logger extends BaseLogger
{
    protected $debugNeeded = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $config = ConfigReader::getConfig();

        // DEBUGGER
        if (!$config->System->debug == false) {
            $writer = new Writer\Stream('php://output');
            $formatter = new \Zend\Log\Formatter\Simple(
                '<pre>%timestamp% %priorityName%: %message%</pre>' . PHP_EOL
            );
            $writer->setFormatter($formatter);
            $this->addWriters(
                $writer,
                'debug-'
                . (is_int($config->System->debug) ? $config->System->debug : '5')
            );
        }
        
        // Activate database logging, if applicable:
        if (isset($config->Logging->database)) {
            $parts = explode(':', $config->Logging->database);
            $table_name = $parts[0];
            $error_types = isset($parts[1]) ? $parts[1] : '';

            $columnMapping = array(
                'priority' => 'priority',
                'message' => 'message',
                'logtime' => 'timestamp',
                'ident' => 'ident'
            );

            // Make Writers
            $filters = explode(',', $error_types);
            $writer = new Writer\Db(
                DbGlobalAdapter::getStaticAdapter(),
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
            $mailer = new Mailer(null, $config);
            $msg = $mailer->getNewMessage()
                ->addFrom($config->Site->email)
                ->addTo($email)
                ->setSubject('VuFind Log Message');

            // Make Writers
            $filters = explode(',', $error_types);
            $writer = new Writer\Mail($msg, $mailer->getTransport());
            $this->addWriters($writer, $filters);
        }

        // Null writer to avoid errors
        if (count($this->writers) == 0) {
            $this->addWriter(new \Zend\Log\Writer\Null());
        }
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
     * Get the logger instance.
     *
     * @return Logger
     */
    public static function getInstance()
    {
        static $instance;
        if (!$instance) {
            $instance = new Logger();
        }
        return $instance;
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
}
