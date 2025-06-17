<?php

namespace VuFind\Log;

use Psr\Log\LoggerInterface;

use function get_class;

/**
 * Extension of PSR-3 LoggerAwareTrait with some convenience methods.
 * This trait provides methods to log messages, now utilizing a PSR-3 compatible logger.
 */
trait LoggerAwareTrait
{
    /**
     * @var LoggerInterface
     * This property will hold the logger instance injected by the ServiceManager.
     */
    protected LoggerInterface $logger;

    /**
     * Sets the logger instance on the object.
     * This method fulfills the contract of VuFind\Log\LoggerAwareInterface.
     *
     * @param  LoggerInterface $logger The logger instance, adhering to PSR-3 standard.
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
        $this->log('error', $msg, $context, $prependClass);
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
        $this->log('warning', $msg, $context, $prependClass);
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
        $this->log('debug', $msg, $context, $prependClass);
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
