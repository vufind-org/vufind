<?php

/**
 * Hierarchy support for record drivers.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Feature;

use function count;
use function in_array;

/**
 * Hierarchy support for record drivers.
 *
 * Assumption: Hierarchy fields found in $this->fields.
 * Assumption: Config object found in $this->mainConfig.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait HierarchyAwareTrait
{
    /**
     * Hierarchy driver plugin manager
     *
     * @var \VuFind\Hierarchy\Driver\PluginManager
     */
    protected $hierarchyDriverManager = null;

    /**
     * Hierarchy driver for current object
     *
     * @var \VuFind\Hierarchy\Driver\AbstractBase
     */
    protected $hierarchyDriver = null;

    /**
     * Get a hierarchy driver appropriate to the current object. (May be false if
     * disabled/unavailable).
     *
     * @return \VuFind\Hierarchy\Driver\AbstractBase|bool
     */
    public function getHierarchyDriver()
    {
        if (
            null === $this->hierarchyDriver
            && null !== $this->hierarchyDriverManager
        ) {
            $type = $this->getHierarchyType();
            $this->hierarchyDriver = $type
                ? $this->hierarchyDriverManager->get($type) : false;
        }
        return $this->hierarchyDriver;
    }

    /**
     * Inject a hierarchy driver plugin manager.
     *
     * @param \VuFind\Hierarchy\Driver\PluginManager $pm Hierarchy driver manager
     *
     * @return object
     */
    public function setHierarchyDriverManager(
        \VuFind\Hierarchy\Driver\PluginManager $pm
    ) {
        $this->hierarchyDriverManager = $pm;
        return $this;
    }

    /**
     * Get the hierarchy_top_id(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyTopID()
    {
        return (array)($this->fields['hierarchy_top_id'] ?? []);
    }

    /**
     * Get the absolute parent title(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyTopTitle()
    {
        return (array)($this->fields['hierarchy_top_title'] ?? []);
    }

    /**
     * Return the collection search ID for this record.
     *
     * @return string
     */
    public function getCollectionSearchId(): string
    {
        return $this->getUniqueID();
    }

    /**
     * Get an associative array (id => title) of collections containing this record.
     *
     * @return array
     */
    public function getContainingCollections()
    {
        // If collections are disabled or this record is not part of a hierarchy, go
        // no further....
        if (
            !isset($this->mainConfig->Collections->collections)
            || !$this->mainConfig->Collections->collections
            || !($hierarchyDriver = $this->getHierarchyDriver())
        ) {
            return false;
        }

        // Initialize some variables needed within the switch below:
        $isCollection = $this->isCollection();
        $titles = $ids = [];

        // Check config setting for what constitutes a collection, act accordingly:
        switch ($hierarchyDriver->getCollectionLinkType()) {
            case 'All':
                if (
                    isset($this->fields['hierarchy_parent_title'])
                    && isset($this->fields['hierarchy_parent_id'])
                ) {
                    $titles = $this->fields['hierarchy_parent_title'];
                    $ids = $this->fields['hierarchy_parent_id'];
                }
                break;
            case 'Top':
                $topTitles = $this->getHierarchyTopTitle();
                $topIDs = $this->getHierarchyTopID();
                if ($topTitles && $topIDs) {
                    foreach ($topIDs as $i => $topId) {
                        // Don't mark an item as its own parent -- filter out parent
                        // collections whose IDs match the current collection's ID.
                        if (
                            !$isCollection
                            || $topId !== $this->fields['is_hierarchy_id']
                        ) {
                            $ids[] = $topId;
                            $titles[] = $topTitles[$i];
                        }
                    }
                }
                break;
        }

        // Map the titles and IDs to a useful format:
        $c = count($ids);
        $retVal = [];
        for ($i = 0; $i < $c; $i++) {
            $retVal[$ids[$i]] = $titles[$i];
        }
        return $retVal;
    }

    /**
     * Get the value of whether or not this is a collection level record
     *
     * NOTE: \VuFind\Hierarchy\TreeDataFormatter\AbstractBase::isCollection()
     * duplicates some of this logic.
     *
     * @return bool
     */
    public function isCollection()
    {
        if (!($hierarchyDriver = $this->getHierarchyDriver())) {
            // Not a hierarchy type record
            return false;
        }

        // Check config setting for what constitutes a collection
        switch ($hierarchyDriver->getCollectionLinkType()) {
            case 'All':
                return isset($this->fields['is_hierarchy_id']);
            case 'Top':
                return isset($this->fields['is_hierarchy_id'])
                    && in_array(
                        $this->fields['is_hierarchy_id'],
                        $this->getHierarchyTopID()
                    );
            default:
                // Default to not be a collection level record
                return false;
        }
    }

    /**
     * Get a list of hierarchy trees containing this record.
     *
     * @param string $hierarchyID The hierarchy to get the tree for
     *
     * @return mixed An associative array of hierarchy trees on success
     * (id => title), false if no hierarchies found
     */
    public function getHierarchyTrees($hierarchyID = false)
    {
        $hierarchyDriver = $this->getHierarchyDriver();
        if ($hierarchyDriver && $hierarchyDriver->showTree()) {
            return $hierarchyDriver->getTreeRenderer($this)
                ->getTreeList($hierarchyID);
        }
        return false;
    }

    /**
     * Get the Hierarchy Type (false if none)
     *
     * @return string|bool
     */
    public function getHierarchyType()
    {
        if (isset($this->fields['hierarchy_top_id'])) {
            $hierarchyType = $this->fields['hierarchytype'] ?? false;
            if (!$hierarchyType) {
                $hierarchyType = $this->mainConfig->Hierarchy->driver ?? false;
            }
            return $hierarchyType;
        }
        return false;
    }
}
