<?php
/**
 * Record tab manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
namespace Finna\RecordTab;

use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;

/**
 * Record tab manager
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class TabManager extends \VuFind\RecordTab\TabManager
{
    /**
     * Get an array of service names by looking up the provided record driver in
     * the provided tab configuration array.
     *
     * @param AbstractRecordDriver $driver Record driver
     *
     * @return array
     */
    public function getTabServices(AbstractRecordDriver $driver)
    {
        return $this->getTabServiceNames($driver);
    }

    /**
     * Get an array of service names by looking up the provided record driver in
     * the provided tab configuration array.
     *
     * @param AbstractRecordDriver $driver Record driver
     *
     * @return array
     */
    protected function getTabServiceNames(AbstractRecordDriver $driver)
    {
        $result = parent::getTabServiceNames($driver);
        // Make sure Details is always the last tab
        if (isset($result['Details'])) {
            $details = $result['Details'];
            unset($result['Details']);
            $result['Details'] = $details;
        }
        return $result;
    }
}
