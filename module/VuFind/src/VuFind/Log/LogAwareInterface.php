<?php
namespace VuFind\Log;

use Psr\Log\LoggerInterface;

/**
 * Interface for classes that are aware of a logger.
 */
interface LoggerAwareInterface
{
    /**
     * Sets the logger instance on the object.
     *
     * @param LoggerInterface $logger The logger instance.
     * @return void
     */
    public function setLogger(LoggerInterface $logger);
}