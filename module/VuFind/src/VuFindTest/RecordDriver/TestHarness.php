<?php
/**
 * Test harness for simulating record drivers (ignore outside of test suite!)
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\RecordDriver;

/**
 * Test harness for simulating record drivers (ignore outside of test suite!)
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class TestHarness extends \VuFind\RecordDriver\AbstractBase
{
    /**
     * Magic method to set/retrieve fields.
     *
     * @param string $method Method name being called.
     * @param array  $params Parameters passed to method.
     *
     * @return mixed
     */
    public function __call($method, $params)
    {
        if (substr($method, 0, 3) == 'get') {
            $index = substr($method, 3);
            return isset($this->fields[$index]) ? $this->fields[$index] : null;
        } else if (substr($method, 0, 3) == 'set') {
            $index = substr($method, 3);
            $this->fields[$index] = $params[0];
        }
        return null;
    }

    /**
     * Get text that can be displayed to represent this record in breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->__call('getBreadcrumb', []);
    }

    /**
     * Return the unique identifier of this record for retrieving additional
     * information (like tags and user comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        return $this->__call('getUniqueID', []);
    }
}
