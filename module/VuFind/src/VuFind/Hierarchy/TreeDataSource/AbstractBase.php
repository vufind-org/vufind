<?php

/**
 * Hierarchy Tree Data Source (abstract base)
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\TreeDataSource;

/**
 * Hierarchy Tree Data Source (abstract base)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractBase implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Hierarchy driver
     *
     * @var \VuFind\Hierarchy\Driver\AbstractBase
     */
    protected $hierarchyDriver = null;

    /**
     * Collection page route.
     *
     * @var string
     */
    protected $collectionRoute = 'collection';

    /**
     * Record page route.
     *
     * @var string
     */
    protected $recordRoute = 'record';

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
     * Get collection page route.
     *
     * @return string
     */
    public function getCollectionRoute()
    {
        return $this->collectionRoute;
    }

    /**
     * Get recordpage route.
     *
     * @return string
     */
    public function getRecordRoute()
    {
        return $this->recordRoute;
    }

    /**
     * Get JSON for the specified hierarchy ID.
     *
     * Build the JSON file from the Solr fields
     *
     * @param string $id      Hierarchy ID.
     * @param array  $options Additional options for JSON generation. (Currently one
     * option is supported: 'refresh' may be set to true to bypass caching).
     *
     * @return string
     */
    abstract public function getJSON($id, $options = []);

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
