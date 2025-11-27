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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log;

use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use VuFind\Net\UserIpReader;

use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

/**
 * This class wraps the BaseLogger class to allow for log verbosity
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Logger implements LoggerInterface, ExtendedLoggerInterface
{
    /**
     * Is debug logging enabled?
     *
     * @var bool
     */
    protected bool $debugNeeded = false;

    /**
     * Monolog logger instance
     *
     * @var MonologLogger
     */
    protected MonologLogger $monologLogger;

    protected const LEVEL_MAP = [
        'crit'       => LogLevel::CRITICAL,
        'err'       => LogLevel::ERROR,
        'warn'      => LogLevel::WARNING,
    ];

    /**
     * Constructor
     *
     * @param UserIpReader   $userIpReader  User IP reader service
     * @param ?MonologLogger $monologLogger Optional Monolog logger instance
     */
    public function __construct(protected UserIpReader $userIpReader, ?MonologLogger $monologLogger)
    {
        $this->monologLogger = $monologLogger ?? new MonologLogger('default');
    }

    /**
     * System is unusable.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * System is unusable.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     *
     * @deprecated
     */
    public function emerg(string|\Stringable $message, array $context = []): void
    {
        $this->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     *
     * @deprecated
     */
    public function crit(string|\Stringable $message, array $context = []): void
    {
        $this->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     *
     * @deprecated
     */
    public function err(string|\Stringable $message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     *
     * @deprecated
     */
    public function warn(string|\Stringable $message, array $context = []): void
    {
        $this->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed              $level   Log level
     * @param string|\Stringable $message Log message
     * @param mixed[]            $context Additional context data
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Map incoming level (e.g., 'err', 'warn') to Monolog/PSR-3 constant.
        $monologLevel = is_string($level) && isset(self::LEVEL_MAP[$level])
            ? self::LEVEL_MAP[$level]
            : $level;

        if (is_array($message)) {
            $context['vufind_log_details'] = $message;
            $mainMonologMessage = 'Exception/Detailed log. See context for levels.';
        } else {
            $mainMonologMessage = $message;
        }
        $this->monologLogger->log($monologLevel, $mainMonologMessage, $context);
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
        $baseError = $error::class . ' : ' . $error->getMessage() . ' at ' . $error->getFile() . ' line '
            . $error->getLine();
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

        $this->log(
            $this->getSeverityFromException($error),
            $baseError,
            [
                'details' => $errorDetails,
            ]
        );
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

        if (
            $error instanceof \VuFind\Exception\HttpStatusInterface
            && in_array($error->getHttpStatus(), [403, 404])
        ) {
            return LogLevel::WARNING;
        }
        return LogLevel::CRITICAL;
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
