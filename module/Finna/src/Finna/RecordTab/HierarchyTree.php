<?php

/**
 * HierarchyTree tab
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace Finna\RecordTab;

/**
 * HierarchyTree tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HierarchyTree extends \VuFind\RecordTab\HierarchyTree
{
    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->driver->tryMethod('getArchiveType') === 'collection'
            ? 'hierarchy_tree_collection' : 'hierarchy_tree_archive';
    }
}
