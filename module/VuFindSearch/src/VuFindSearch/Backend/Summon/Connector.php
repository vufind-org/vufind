<?php
/**
 * Summon Search API Interface (VuFind implementation)
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2009.
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
 * @package  Summon
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
namespace VuFindSearch\Backend\Summon;
use SerialsSolutions\Summon\Zend2 as BaseSummon, Zend\Log\LoggerInterface;

/**
 * Summon Search API Interface (VuFind implementation)
 *
 * @category VuFind2
 * @package  Summon
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class Connector extends BaseSummon implements \Zend\Log\LoggerAwareInterface
{
    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Print a message if debug is enabled.
     *
     * @param string $msg Message to print
     *
     * @return void
     */
    protected function debugPrint($msg)
    {
        if ($this->logger) {
            $this->logger->debug("$msg\n");
        }
    }
}
