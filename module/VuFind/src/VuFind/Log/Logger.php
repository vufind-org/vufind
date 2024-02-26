<?php

/**
 * VuFind Logger
 *
 * PHP version 8
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

use Laminas\Log\Logger as BaseLogger;
use Laminas\Log\Writer\WriterInterface;
use Laminas\Stdlib\SplPriorityQueue;
use Traversable;
use VuFind\Net\UserIpReader;

use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;

/**
 * This class wraps the BaseLogger class to allow for log verbosity
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Logger extends BaseLogger
{
    /**
     * Is debug logging enabled?
     *
     * @var bool
     */
    protected $debugNeeded = false;

    /**
     * User IP address reader
     *
     * @var UserIpReader
     */
    protected $userIpReader;

    /**
     * Constructor
     *
     * Set options for a logger. Accepted options are:
     * - writers: array of writers to add to this logger
     * - exceptionhandler: if true register this logger as exceptionhandler
     * - errorhandler: if true register this logger as errorhandler
     *
     * @param UserIpReader      $userIpReader User IP reader
     * @param array|Traversable $options      Configuration options
     *
     * @throws \Laminas\Log\Exception\InvalidArgumentException
     */
    public function __construct(UserIpReader $userIpReader, $options = null)
    {
        $this->userIpReader = $userIpReader;
        parent::__construct($options);
    }

    /**
     * Is one of the log writers listening for debug messages?  (This is useful to
     * know, since some code can save time that would be otherwise wasted generating
     * debug messages if we know that no one is listening).
     *
     * @param bool $newState New state (omit to leave current state unchanged)
     *
     * @return bool
     */
    public function debugNeeded($newState = null)
    {
        if (null !== $newState) {
            $this->debugNeeded = $newState;
        }
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
                        'priority'     => (int)$priority,
                        'priorityName' => $this->priorities[$priority],
                        'message'      => $message,
                        'extra'        => $extra,
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
        // If the exception provides the severity level, use it:
        if ($error instanceof \VuFind\Exception\SeverityLevelInterface) {
            return $error->getSeverityLevel();
        }
        // Treat unexpected or 5xx errors as more severe than 4xx errors.
        if (
            $error instanceof \VuFind\Exception\HttpStatusInterface
            && in_array($error->getHttpStatus(), [403, 404])
        ) {
            return BaseLogger::WARN;
        }
        return BaseLogger::CRIT;
    }

    /**
     * Log an exception triggered by the framework for administrative purposes.
     *
     * @param \Exception                 $error  Exception to log
     * @param \Laminas\Stdlib\Parameters $server Server metadata
     *
     * @return void
     */
    public function logException($error, $server)
    {
        // We need to build a variety of pieces so we can supply
        // information at five different verbosity levels:
        $baseError = $error::class . ' : ' . $error->getMessage();
        $prev = $error->getPrevious();
        while ($prev) {
            $baseError .= ' ; ' . $prev::class . ' : ' . $prev->getMessage();
            $prev = $prev->getPrevious();
        }
        $referer = $server->get('HTTP_REFERER', 'none');
        $ipAddr = $this->userIpReader->getUserIp();
        $basicServer
            = '(Server: IP = ' . $ipAddr . ', '
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
            5 => $baseError . $detailedServer . $detailedBacktrace,
        ];

        $this->log($this->getSeverityFromException($error), $errorDetails);
    }

    /**
     * Remove a writer.
     *
     * @param WriterInterface $writer Writer to remove
     *
     * @return void
     */
    public function removeWriter(WriterInterface $writer): void
    {
        $newQueue = new SplPriorityQueue();
        foreach ($this->getWriters() as $i => $current) {
            if ($current !== $writer) {
                $newQueue->insert($current, $i);
            }
        }
        $this->setWriters($newQueue);
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
            return $arg::class . ' Object';
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
        if (null === $arg) {
            return 'null';
        }
        return "'$arg'";
    }
}
