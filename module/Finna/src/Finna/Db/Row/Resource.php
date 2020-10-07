<?php
/**
 * Row Definition for resource
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Db\Row;

/**
 * Row Definition for resource
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Resource extends \VuFind\Db\Row\Resource
{
    /**
     * Use a record driver to assign metadata to the current row.  Return the
     * current object to allow fluent interface.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    The record driver
     * @param \VuFind\Date\Converter            $converter Date converter
     *
     * @return \VuFind\Db\Row\Resource
     */
    public function assignMetadata($driver, \VuFind\Date\Converter $converter)
    {
        parent::assignMetadata($driver, $converter);
        if (empty($this->year)) {
            $this->year = $driver->tryMethod('getYear')
                ? $driver->tryMethod('getYear') : null;
        }
        return $this;
    }

    /**
     * Save
     *
     * @return int
     */
    public function save()
    {
        // Save only record id for restricted Solr R2 records
        if ($this->source === 'R2') {
            $this->title = '';
            $this->author = $this->year = $this->extra_metadata = null;
        }
        return parent::save();
    }
}
