<?php

/**
 * VuFind Logger Proxy
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log;

use Psr\Log\LoggerInterface;
use Stringable;

use function call_user_func_array;
use function func_get_args;

/**
 * Lazy-loading proxy for the VuFind logger.
 *
 * This class delays the instantiation of the actual VuFind\Log\Logger
 * until a logging method is first called, improving performance
 * by only initializing the full logging stack when truly necessary.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LoggerProxy implements LoggerInterface, ExtendedLoggerInterface
{
    /**
     * Callback for creating the actual class
     *
     * @var callable
     */
    protected $callback;

    /**
     * Logger implementation
     *
     * @var Logger
     */
    protected $logger = null;

    /**
     * Constructor
     *
     * @param callable $callback Callback for creating the actual class. The first argument is passed by
     * reference and should be set to a PSR-3 logger by the callback (in order to populate $this->logger).
     * This proxy class will be passed as the second argument in case it is needed by the callback.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Log an emergency
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log an emergency
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     *
     * @deprecated
     */
    public function emerg(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log an alert
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log a critical error
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log a critical error
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params
     *
     * @return void
     *
     * @deprecated
     */
    public function crit(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log an error
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function error(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log an error
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params
     *
     * @return void
     *
     * @deprecated
     */
    public function err(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log a warning
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log a warning
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params
     *
     * @return void
     *
     * @deprecated
     */
    public function warn(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log a notice
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log an info message
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function info(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log a debug message
     *
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed             $level   PSR-3 log level (LogLevel constant or string)
     * @param Stringable|string $message Message
     * @param array             $context Extra params (context from PSR-3)
     *
     * @return void
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Log an exception triggered by the framework for administrative purposes.
     * (Part of ExtendedLoggerInterface)
     *
     * @param \Exception                 $error  Exception to log
     * @param \Laminas\Stdlib\Parameters $server Server metadata
     *
     * @return void
     */
    public function logException($error, $server): void
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Proxy any other Logger method
     *
     * @param string $methodName The name of the called method
     * @param array  $params     Array of passed parameters
     *
     * @return mixed Varies by method
     */
    public function __call($methodName, $params)
    {
        return call_user_func_array([$this->getLogger(), $methodName], $params);
    }

    /**
     * Get logger
     *
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        if (null === $this->logger) {
            // The callback is responsible for setting $this->logger to an instance of VuFind\Log\Logger
            ($this->callback)($this->logger, $this);
        }
        return $this->logger;
    }
}
