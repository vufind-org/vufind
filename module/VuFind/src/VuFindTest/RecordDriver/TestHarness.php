<?php

/**
 * Test harness for simulating record drivers (ignore outside of test suite!)
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

/**
 * Test harness for simulating record drivers (ignore outside of test suite!)
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TestHarness extends \VuFind\RecordDriver\AbstractBase
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setSourceIdentifiers('Solr');
    }

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
        if (str_starts_with($method, 'get')) {
            $index = substr($method, 3);
            return $this->fields[$index] ?? null;
        } elseif (str_starts_with($method, 'set')) {
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

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier()
    {
        // For consistency with other methods, allow SourceIdentifier to be
        // overridden via rawData (but also allow the "normal" method as a
        // fallback):
        return isset($this->fields['SourceIdentifier'])
            ? $this->__call('getSourceIdentifier', [])
            : parent::getSourceIdentifier();
    }
}
