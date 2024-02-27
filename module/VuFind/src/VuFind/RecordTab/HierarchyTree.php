<?php

/**
 * HierarchyTree tab
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use function count;
use function is_object;

/**
 * HierarchyTree tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HierarchyTree extends AbstractBase
{
    /**
     * Tree data
     *
     * @var array
     */
    protected $treeList = null;

    /**
     * Configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config Configuration
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get the VuFind configuration.
     *
     * @return \Laminas\Config\Config
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'hierarchy_tree';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $trees = $this->getTreeList();
        return !empty($trees);
    }

    /**
     * Get the ID of the active tree (false if none)
     *
     * @return string|bool
     */
    public function getActiveTree()
    {
        $treeList = $this->getTreeList();
        $hierarchySetting = ($request = $this->getRequest())
            ? $request->getPost('hierarchy', $request->getQuery('hierarchy', false))
            : false;
        if (count($treeList) == 1 || !$hierarchySetting) {
            $keys = array_keys($treeList);
            return $keys[0];
        } else {
            return $hierarchySetting;
        }
    }

    /**
     * Get an array of tree data
     *
     * @return array
     */
    public function getTreeList()
    {
        if (null === $this->treeList) {
            $this->treeList
                = $this->getRecordDriver()->tryMethod('getHierarchyTrees');
            if (null === $this->treeList) {
                $this->treeList = [];
            }
        }
        return $this->treeList;
    }

    /**
     * Should we display the full tree, or just a partial tree?
     *
     * @return bool
     */
    public function isFullHierarchyVisible()
    {
        // Get hierarchy driver:
        $recordDriver = $this->getRecordDriver();
        $hierarchyDriver = $recordDriver->tryMethod('getHierarchyDriver');

        // We need a driver to proceed:
        if (is_object($hierarchyDriver)) {
            // No setting, or true setting -- use default setting:
            $settings = $hierarchyDriver->getTreeSettings();
            if ($settings['fullHierarchyRecordView'] ?? true) {
                return true;
            }
        }

        // Currently displaying top of tree?  Disable partial hierarchy:
        if ($this->getActiveTree() == $recordDriver->getUniqueId()) {
            return true;
        }

        // Only if we got this far is it appropriate to use a partial hierarchy:
        return false;
    }

    /**
     * Render a hierarchy tree
     *
     * @param string  $id      Hierarchy ID (omit to use active tree)
     * @param ?string $context Context for use by renderer or null for default
     * @param array   $options Additional options (like previewElement)
     *
     * @return string
     */
    public function renderTree(string $id = null, ?string $context = null, array $options = [])
    {
        $id ??= $this->getActiveTree();
        $recordDriver = $this->getRecordDriver();
        $hierarchyDriver = $recordDriver->tryMethod('getHierarchyDriver');
        if (is_object($hierarchyDriver)) {
            return $hierarchyDriver->render($recordDriver, $context ?? 'Record', 'List', $id, $options);
        }
        return '';
    }

    /**
     * Is tree searching active?
     *
     * @return bool
     */
    public function searchActive()
    {
        $config = $this->getConfig();
        return !isset($config->Hierarchy->search) || $config->Hierarchy->search;
    }

    /**
     * Get the tree search result limit.
     *
     * @return int
     */
    public function getSearchLimit()
    {
        $config = $this->getConfig();
        return $config->Hierarchy->treeSearchLimit ?? -1;
    }

    /**
     * Get the current active record. Returns record driver if there is an active
     * record or null otherwise.
     *
     * @return ?\VuFind\RecordDriver\AbstractBase
     */
    public function getActiveRecord(): ?\VuFind\RecordDriver\AbstractBase
    {
        return null;
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // No, special width adjustment needed.
        return false;
    }
}
