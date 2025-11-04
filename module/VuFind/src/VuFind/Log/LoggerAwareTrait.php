<?php

/**
 * Implementation of PSR-3 \Psr\Log\LoggerAwareTrait with some additional convenience methods.
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
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function get_class;

/**
 * Implementation of PSR-3 \Psr\Log\LoggerAwareTrait with some additional convenience methods.
 * This trait provides methods to log messages, now utilizing a PSR-3 compatible logger.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait LoggerAwareTrait
{
    use VarDumperTrait;

    /**
     * This property will hold the logger instance injected by the ServiceManager.
     *
     * @var LoggerInterface
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Sets the logger instance on the object.
     * This method fulfills the contract of Psr\Log\LoggerAwareInterface.
     *
     * @param LoggerInterface $logger The logger instance, adhering to PSR-3 standard.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Log an error message.
     *
     * @param string $msg          Log message
     * @param array  $context      Log context
     * @param bool   $prependClass Prepend class name to message?
     *
     * @return void
     */
    protected function logError($msg, array $context = [], $prependClass = true)
    {
        $this->log(LogLevel::ERROR, $msg, $context, $prependClass);
    }

    /**
     * Log an exception
     *
     * @param \Exception $exception Exception to log
     *
     * @return void
     */
    public function logException(\Exception $exception): void
    {
        if ($this->logger instanceof \VuFind\Log\Logger) {
            $this->logger->logException($exception, new \Laminas\Stdlib\Parameters());
        }
    }

    /**
     * Log a warning message.
     *
     * @param string $msg          Log message
     * @param array  $context      Log context
     * @param bool   $prependClass Prepend class name to message?
     *
     * @return void
     */
    protected function logWarning($msg, array $context = [], $prependClass = true)
    {
        $this->log(LogLevel::WARNING, $msg, $context, $prependClass);
    }

    /**
     * Log a debug message.
     *
     * @param string $msg          Log message
     * @param array  $context      Log context
     * @param bool   $prependClass Prepend class name to message?
     *
     * @return void
     */
    protected function debug($msg, array $context = [], $prependClass = true)
    {
        $this->log(LogLevel::DEBUG, $msg, $context, $prependClass);
    }

    /**
     * Send a message to the logger.
     *
     * @param string $level        Log level (e.g., 'error', 'warning', 'debug')
     * @param string $message      Log message
     * @param array  $context      Log context
     * @param bool   $prependClass Prepend class name to message?
     *
     * @return void
     */
    protected function log(
        $level,
        $message,
        array $context = [],
        $prependClass = false
    ) {
        if ($this->logger) {
            if ($prependClass) {
                $message = get_class($this) . ': ' . $message;
            }
            $this->logger->log($level, $message, $context);
        }
    }
}
