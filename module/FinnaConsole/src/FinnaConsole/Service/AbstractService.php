<?php
/**
 * Abstract base class for console services.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Abstract base class for console services.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractService implements ConsoleServiceInterface
{
    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger = null;

    /**
     * Error logger
     *
     * @var Logger
     */
    protected $errLogger = null;

    /**
     * Init logging.
     *
     * @return void
     */
    public function initLogging()
    {
        $writer = new Stream('php://output');
        $this->logger = new Logger();
        $this->logger->addWriter($writer);

        $writer = new Stream('php://stderr');
        $this->errLogger = new Logger();
        $this->errLogger->addWriter($writer);
    }

    /**
     * Output a message with a timestamp
     *
     * @param string  $msg   Message
     * @param boolean $error Log as en error message?
     *
     * @return void
     */
    protected function msg($msg, $error = false)
    {
        $msg = '[' . getmypid() . "] $msg";
        if ($error) {
            $this->errLogger->err($msg);
            $this->logger->err($msg);
        } else {
            $this->logger->info($msg);
        }
    }

    /**
     * Output an error message with a timestamp
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function err($msg)
    {
        $this->msg($msg, true);
    }
}
