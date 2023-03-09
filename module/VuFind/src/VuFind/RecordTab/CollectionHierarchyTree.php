<?php
/**
 * HierarchyTree tab
 *
 * PHP version 7
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

/**
 * HierarchyTree tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class CollectionHierarchyTree extends HierarchyTree
{
    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $loader;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config Configuration
     * @param \VuFind\Record\Loader  $loader Record loader
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \VuFind\Record\Loader $loader
    ) {
        parent::__construct($config);
        $this->loader = $loader;
    }

    /**
     * Render a hierarchy tree
     *
     * @param string $baseUrl Base URL to use in links within tree
     * @param string $id      Hierarchy ID (omit to use active tree)
     * @param string $context Context for use by renderer
     *
     * @return string
     */
    public function renderTree($baseUrl, $id = null, $context = 'Collection')
    {
        // Same as parent -- we just have a different default context:
        return parent::renderTree($baseUrl, $id, $context);
    }

    /**
     * Get the current active record.  Returns record driver if found, false
     * if no record requested, null if ID invalid.
     *
     * @return mixed
     */
    public function getActiveRecord()
    {
        $id = $this->getRequest()->getQuery('recordID', false);
        if ($id === false) {
            return $id;
        }
        try {
            return $this->loader->load($id);
        } catch (\VuFind\Exception\RecordMissing $e) {
            return null;
        }
    }
}
