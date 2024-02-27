<?php

/**
 * Hierarchy interface.
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
 * @package  Hierarchy
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\Driver;

use VuFind\Hierarchy\TreeDataSource\PluginManager as DataManager;
use VuFind\Hierarchy\TreeRenderer\PluginManager as RendererManager;

/**
 * Hierarchy interface class.
 *
 * Interface Hierarchy based drivers.
 * This should be extended to implement functionality for specific
 * Hierarchy Systems (i.e. Calm etc.).
 *
 * @category VuFind
 * @package  Hierarchy
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractBase
{
    /**
     * Driver configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Tree data source plugin manager
     *
     * @var DataManager
     */
    protected $dataManager;

    /**
     * Are trees globally enabled?
     *
     * @var bool
     */
    protected $enabled = true;

    /**
     * Tree renderer plugin manager
     *
     * @var RendererManager
     */
    protected $rendererManager;

    /**
     * Find out whether or not to show the tree
     *
     * @return bool
     */
    abstract public function showTree();

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config          Configuration
     * @param DataManager            $dataManager     Tree data source plugin manager
     * @param RendererManager        $rendererManager Tree renderer plugin manager
     * @param array                  $options         Extra options (if any)
     */
    public function __construct(
        \Laminas\Config\Config $config,
        DataManager $dataManager,
        RendererManager $rendererManager,
        $options = []
    ) {
        $this->config = $config;
        $this->dataManager = $dataManager;
        $this->rendererManager = $rendererManager;
        if (isset($options['enabled'])) {
            $this->enabled = (bool)$options['enabled'];
        }
    }

    /**
     * Returns the Source of the Tree
     *
     * @return object The tree data source object
     */
    public function getTreeSource()
    {
        $source = $this->dataManager->get($this->getTreeSourceType());
        $source->setHierarchyDriver($this);
        return $source;
    }

    /**
     * Returns the actual object for generating trees
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return object
     */
    public function getTreeRenderer(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $renderer = $this->rendererManager->get($this->getTreeRendererType());
        $renderer->setRecordDriver($driver);
        return $renderer;
    }

    /**
     * Render the tree for a given record.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver      Record driver
     * @param string                            $context     Context in which the tree is being created
     * @param string                            $mode        Type of tree required
     * @param string                            $hierarchyID Hierarchy ID to get the tree for
     * @param array                             $options     Additional options for the renderer
     *
     * @return string
     */
    public function render(
        \VuFind\RecordDriver\AbstractBase $driver,
        string $context,
        string $mode,
        string $hierarchyID,
        array $options
    ) {
        if (!$this->showTree()) {
            return false;
        }
        return $this->getTreeRenderer($driver)
            ->render($context, $mode, $hierarchyID, $driver->getUniqueID(), $options);
    }

    /**
     * Returns the Tree Renderer Type
     *
     * @return string
     */
    abstract public function getTreeRendererType();

    /**
     * Get Tree Settings
     *
     * Returns all the configuration settings for a hierarchy tree
     *
     * @return array The values of the configuration setting
     */
    abstract public function getTreeSettings();

    /**
     * Get Tree Data Source Type
     *
     * @return string
     */
    abstract public function getTreeSourceType();

    /**
     * Check if sorting is enabled in the hierarchy Options
     *
     * @return bool
     */
    abstract public function treeSorting();

    /**
     * Get Collection Link Type
     *
     * @return string
     */
    abstract public function getCollectionLinkType();

    /**
     * Get tree cache time in seconds
     *
     * @return int
     */
    abstract public function getTreeCacheTime();
}
