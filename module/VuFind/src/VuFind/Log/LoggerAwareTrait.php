<?php
/**
 * Extension of \Zend\Log\LoggerAwareTrait with some convenience methods.
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

/**
 * Extension of \Zend\Log\LoggerAwareTrait with some convenience methods.
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
trait LoggerAwareTrait
{
    use \Zend\Log\LoggerAwareTrait;

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
        return $this->log('err', $msg, $context, $prependClass);
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
        return $this->log('warn', $msg, $context, $prependClass);
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
        return $this->log('debug', $msg, $context, $prependClass);
    }

    /**
     * Send a message to the logger.
     *
     * @param string $level        Log level
     * @param string $message      Log message
     * @param array  $context      Log context
     * @param bool   $prependClass Prepend class name to message?
     *
     * @return void
     */
    protected function log($level, $message, array $context = [],
        $prependClass = false
    ) {
        if ($this->logger) {
            if ($prependClass) {
                $message = get_class($this) . ': ' . $message;
            }
            $this->logger->$level($message, $context);
        }
    }
}