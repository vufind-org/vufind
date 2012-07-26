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
use VuFind\Config\Reader as ConfigReader,
    Zend\Log\Logger as BaseLogger, Zend\Log\Filter\Priority as PriorityFilter;

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
    protected static $debugNeeded = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $config = ConfigReader::getConfig();
        /* TODO:
        // DEBUGGER
        if (!$config->System->debug == false) {
            $writer = new VF_Log_Writer_Stream('php://output');
            $formatter = new Zend_Log_Formatter_Simple(
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
            $writer = new VF_Log_Writer_Db(
                Zend_Db_Table::getDefaultAdapter(),
                $table_name,
                $columnMapping
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
            $writer = new VF_Log_Writer_Stream($file);
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
            $mailer = new Zend_Mail('UTF-8');
            $mailer->setFrom($config->Site->email);
            $mailer->setSubject('VuFind Log Message');
            $mailer->addTo($email);

            // Make Writers
            $filters = explode(',', $error_types);
            $writer = new VF_Log_Writer_Mail($mailer);
            $this->addWriters($writer, $filters);
        }

        // Null writer to avoid errors
        if (count($this->_writers) == 0) {
            $this->addWriter(new VF_Log_Writer_Null());
        }
         */
    }

    /**
     * Applies an array of filters to a writer
     *
     * Filter keys: alert, error, notice, debug
     *
     * @param Zend_Log_Writer_Abstract $writer  The writer to apply the filters to
     * @param string|array             $filters An array or comma-separated string of
     * logging levels
     *
     * @return \Zend\Log\Writer $writer
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
                self::$debugNeeded = true;

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
                $newWriter->setVerbosity($verbosity);
            }

            // filtering -- only log messages between the min and max priority levels
            $filter1 = new PriorityFilter($min, '<=');
            $filter2 = new PriorityFilter($max, '>=');
            $newWriter->addFilter($filter1);
            $newWriter->addFilter($filter2);

            // add the writer
            $this->addWriter($newWriter);
        }
    }

    /**
     * Log a message at the debug priority level
     *
     * @param string $msg Debug message
     *
     * @return void
     */
    public static function getInstance($msg)
    {
        static $instance;
        if (!$instance) {
            $instance = new Manager();
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
    public static function debugNeeded()
    {
        return self::$debugNeeded;
    }
}
