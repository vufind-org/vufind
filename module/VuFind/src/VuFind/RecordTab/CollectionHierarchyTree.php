<?php

/**
 * HierarchyTree tab
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
     * @param string  $id      Hierarchy ID (omit to use active tree)
     * @param ?string $context Context for use by renderer or null for default
     * @param array   $options Additional options (like previewElement)
     *
     * @return string
     */
    public function renderTree(string $id = null, ?string $context = null, array $options = [])
    {
        // Same as parent -- we just have a different default context:
        return parent::renderTree($id, $context ?? 'Collection', $options);
    }

    /**
     * Get the current active record. Returns record driver if there is an active
     * record or null otherwise.
     *
     * @return ?\VuFind\RecordDriver\AbstractBase
     */
    public function getActiveRecord(): ?\VuFind\RecordDriver\AbstractBase
    {
        $id = $this->getRequest()->getQuery('recordID');
        if (null === $id) {
            return $this->driver;
        }
        try {
            return $this->loader->load($id);
        } catch (\VuFind\Exception\RecordMissing $e) {
            return null;
        }
    }
}
