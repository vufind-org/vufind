<?php
/**
 * Record tab plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace Finna\RecordTab;

use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;

/**
 * Record tab plugin manager
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class PluginManager extends \VuFind\RecordTab\PluginManager
{
    /**
     * Get a default tab by looking up the provided record driver in the tab
     * configuration array.
     *
     * @param AbstractRecordDriver $driver   Record driver
     * @param array                $config   Tab configuration (map of
     * driver class => tab configuration)
     * @param array                $tabs     Details on available tabs (returned
     * from getTabsForRecord()).
     * @param string               $fallback Fallback to use if no tab specified
     * or matched.
     *
     * @return string
     */
    public function getDefaultTabForRecord(AbstractRecordDriver $driver,
        array $config, array $tabs, $fallback = null
    ) {
        $result = parent::getDefaultTabForRecord($driver, $config, $tabs, $fallback);
        if ('Details' === $result) {
            $result = '';
        }
        return $result;
    }
}
