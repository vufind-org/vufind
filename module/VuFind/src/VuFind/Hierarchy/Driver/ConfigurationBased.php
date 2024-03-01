<?php

/**
 * Configuration-Based Hierarchy Driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Hierarchy_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\Driver;

/**
 * Configuration-Based Hierarchy Driver
 *
 * @category VuFind
 * @package  Hierarchy_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ConfigurationBased extends AbstractBase
{
    /**
     * Default tree renderer
     *
     * @var string
     */
    protected $defaultTreeRenderer = 'HTMLTree';

    /**
     * Show Tree
     *
     * Returns the configuration setting for displaying a hierarchy tree
     *
     * @return bool The boolean value of the configuration setting
     */
    public function showTree()
    {
        $treeConfigDriver = $this->config->HierarchyTree->show ?? false;
        return $this->enabled && $treeConfigDriver;
    }

    /**
     * Get Tree Renderer Type
     *
     * Returns the configuration setting for generating a hierarchy tree
     *
     * @return string The value of the configuration setting
     */
    public function getTreeRendererType()
    {
        return $this->config->HierarchyTree->treeRenderer
            ?? $this->defaultTreeRenderer;
    }

    /**
     * Get Tree Data Source Type
     *
     * @return string
     */
    public function getTreeSourceType()
    {
        return $this->config->HierarchyTree->treeSource ?? 'Solr';
    }

    /**
     * Get Tree Cache Time
     *
     * Returns the configuration setting for hierarchy tree caching time when
     * using solr to build the tree
     *
     * @return int The value of the configuration setting
     */
    public function getTreeCacheTime()
    {
        return $this->config->HierarchyTree->solrCacheTime ?? 43200;
    }

    /**
     * Check if sorting is enabled in the hierarchy Options
     *
     * Returns the configuration setting for hierarchy tree sorting
     *
     * @return bool The value of the configuration setting
     */
    public function treeSorting()
    {
        return $this->config->HierarchyTree->sorting ?? false;
    }

    /**
     * Get Tree Settings
     *
     * Returns all the configuration settings for a hierarchy tree
     *
     * @return array The values of the configuration setting
     */
    public function getTreeSettings()
    {
        return isset($this->config->HierarchyTree)
            ? $this->config->HierarchyTree->toArray() : [];
    }

    /**
     * Get Collection Link Type from the config file
     *
     * @return string
     */
    public function getCollectionLinkType()
    {
        return isset($this->config->Collections->link_type)
            ? ucwords(strtolower($this->config->Collections->link_type)) : 'All';
    }

    /**
     * Get the Solr field name used for grouping together collection contents
     *
     * @param bool $hasSearch Is the user performing a search?
     *
     * @return string
     */
    public function getCollectionField(bool $hasSearch): string
    {
        if ($hasSearch && null !== ($field = $this->config->Collections->search_container_id_field ?? null)) {
            return $field;
        }
        return match ($this->getCollectionLinkType()) {
            'All' => 'hierarchy_parent_id',
            'Top' => 'hierarchy_top_id',
        };
    }
}
