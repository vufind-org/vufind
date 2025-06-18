<?php
namespace VuFind\Log;

use Psr\Log\LoggerAwareInterface as PSRLoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Interface for classes that are aware of a logger.
 */
interface LoggerAwareInterface extends PSRLoggerAwareInterface
{
    /**
     * Sets the logger instance on the object.
     *
     * @param LoggerInterface $logger The logger instance.
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void;
}