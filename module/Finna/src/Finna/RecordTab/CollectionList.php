<?php

/**
 * Collection list tab
 *
 * PHP version 8
 *
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace Finna\RecordTab;

/**
 * Collection list tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class CollectionList extends \VuFind\RecordTab\CollectionList
{
    /**
     * Is this tab active?
     * Override to allow this tab to be displayed for records which are part of a collection.
     *
     * @return bool
     */
    public function isActive()
    {
        $driver = $this->getRecordDriver();
        return $driver->tryMethod('isCollection') || $driver->tryMethod('getContainingCollections');
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->driver->tryMethod('getArchiveType') === 'collection'
            ? 'Collection Items' : 'Archive Content';
    }
}
