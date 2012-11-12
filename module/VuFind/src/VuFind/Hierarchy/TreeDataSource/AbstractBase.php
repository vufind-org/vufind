<?php
/**
 * Hierarchy Tree Data Source (abstract base)
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
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
namespace VuFind\Hierarchy\TreeDataSource;
use Zend\Log\LoggerInterface;

/**
 * Hierarchy Tree Data Source (abstract base)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind2
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
abstract class AbstractBase implements \Zend\Log\LoggerAwareInterface
{
    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Hierarchy driver
     *
     * @var \VuFind\Hierarchy\Driver\AbstractBase
     */
    protected $hierarchyDriver = null;

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
     * Output a debug message, if appropriate
     *
     * @param string $msg Message to display
     *
     * @return void
     * @access protected
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * Get the hierarchy driver
     *
     * @return \VuFind\Hierarchy\Driver\AbstractBase
     * @throws \Exception
     */
    protected function getHierarchyDriver()
    {
        if (null === $this->hierarchyDriver) {
            throw new \Exception('Missing hierarchy driver');
        }
        return $this->hierarchyDriver;
    }

    /**
     * Set the hierarchy driver
     *
     * @param \VuFind\Hierarchy\Driver\AbstractBase $driver Hierarchy driver
     *
     * @return AbstractBase
     */
    public function setHierarchyDriver(\VuFind\Hierarchy\Driver\AbstractBase $driver)
    {
        $this->hierarchyDriver = $driver;
        return $this;
    }

    /**
     * Get XML for the specified hierarchy ID.
     *
     * @param string $id      Hierarchy ID.
     * @param array  $options Additional options for XML generation.
     *
     * @return string
     */
    abstract public function getXML($id, $options = array());

    /**
     * Does this data source support the specified hierarchy ID?
     *
     * @param string $id Hierarchy ID.
     *
     * @return bool
     */
    abstract public function supports($id);
}

?>