<?php

/**
 * Hierarchy Tree Renderer
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
 * @package  HierarchyTree_Renderer
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\TreeRenderer;

use function is_object;

/**
 * Hierarchy Tree Renderer
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind
 * @package  HierarchyTree_Renderer
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractBase
{
    /**
     * Hierarchical record to work on
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $recordDriver = null;

    /**
     * Source of hierarchy data
     *
     * @var \VuFind\Hierarchy\TreeDataSource\AbstractBase
     */
    protected $dataSource = null;

    /**
     * Set the record driver to operate on
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return AbstractBase
     */
    public function setRecordDriver(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $this->recordDriver = $driver;
        return $this;
    }

    /**
     * Get the current record driver
     *
     * @return \VuFind\RecordDriver\DefaultRecord
     * @throws \Exception
     */
    protected function getRecordDriver()
    {
        if (null === $this->recordDriver) {
            throw new \Exception('Missing record driver object');
        }
        return $this->recordDriver;
    }

    /**
     * Get the current hierarchy data source
     *
     * @return \VuFind\Hierarchy\TreeDataSource\AbstractBase
     * @throws \Exception
     */
    protected function getDataSource()
    {
        if (null === $this->dataSource) {
            // Load the hierarchy driver from the record driver -- throw exception if
            // this fails, since we shouldn't be using this class for drivers that do
            // not support hierarchies!
            $hierarchyDriver = $this->getRecordDriver()
                ->tryMethod('getHierarchyDriver');
            if (!is_object($hierarchyDriver)) {
                throw new \Exception(
                    'Cannot load hierarchy driver from record driver.'
                );
            }
            $this->dataSource = $hierarchyDriver->getTreeSource();
        }
        return $this->dataSource;
    }

    /**
     * Get a list of trees containing the item represented by the stored record
     * driver.
     *
     * @param string $hierarchyID Optional filter: specific hierarchy ID to retrieve
     *
     * @return mixed An array of hierarchy IDS if a hierarchy tree exists,
     * false if it does not
     */
    abstract public function getTreeList($hierarchyID = false);

    /**
     * Render the Hierarchy Tree
     *
     * @param string  $context     The context from which the call has been made
     * @param string  $mode        The mode in which the tree should be generated
     * @param string  $hierarchyID The hierarchy ID of the tree to fetch (optional)
     * @param ?string $selectedID  The current record ID (optional)
     *
     * @return mixed The desired hierarchy tree output (or false on error)
     */
    abstract public function render(
        string $context,
        string $mode,
        string $hierarchyID,
        ?string $selectedID = null
    );

    /**
     * Get Hierarchy Name
     *
     * @param string $hierarchyID        The hierarchy ID to find the title for
     * @param array  $inHierarchies      An array of hierarchy IDs
     * @param array  $inHierarchiesTitle An array of hierarchy Titles
     *
     * @return string A hierarchy title
     */
    public function getHierarchyName(
        $hierarchyID,
        $inHierarchies,
        $inHierarchiesTitle
    ) {
        $keys = array_flip($inHierarchies);
        $key = $keys[$hierarchyID];
        return $inHierarchiesTitle[$key];
    }
}
