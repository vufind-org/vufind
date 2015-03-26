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
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeDataSource;

/**
 * Hierarchy Tree Data Source (abstract base)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind2
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
abstract class AbstractBase implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Hierarchy driver
     *
     * @var \VuFind\Hierarchy\Driver\AbstractBase
     */
    protected $hierarchyDriver = null;

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
    abstract public function getXML($id, $options = []);

    /**
     * Does this data source support the specified hierarchy ID?
     *
     * @param string $id Hierarchy ID.
     *
     * @return bool
     */
    abstract public function supports($id);
}