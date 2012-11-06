<?php
/**
 * HierarchyTree tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
namespace VuFind\RecordTab;
use VuFind\Config\Reader as ConfigReader;

/**
 * HierarchyTree tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
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
        if (count($treeList) == 1) {
            $keys = array_keys($treeList);
            return $keys[0];
        } else {
            return ($request = $this->getRequest())
                ? $request->getQuery('hierarchy', false)
                : false;
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
                $this->treeList = array();
            }
        }
        return $this->treeList;
    }

    /**
     * Render a hierarchy tree
     *
     * @param string $baseUrl Base URL to use in links within tree
     * @param string $id      Hierarchy ID (omit to use active tree)
     *
     * @return string
     */
    public function renderTree($baseUrl, $id = null)
    {
        $id = (null === $id) ? $this->getActiveTree() : $id;
        $recordDriver = $this->getRecordDriver();
        $hierarchyDriver = $recordDriver->tryMethod('getHierarchyDriver');
        if (is_object($hierarchyDriver)) {
            $tree = $hierarchyDriver->render($recordDriver, 'Record', 'List', $id);
            return str_replace(
                '%%%%VUFIND-BASE-URL%%%%', rtrim($baseUrl, '/'), $tree
            );
        }
        return '';
    }
}