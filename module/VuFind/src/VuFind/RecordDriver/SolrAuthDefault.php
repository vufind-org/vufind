<?php

/**
 * Model for Solr authority records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use function is_array;

/**
 * Model for Solr authority records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrAuthDefault extends SolrDefault
{
    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        // No difference between short and long titles for authority records:
        return $this->getTitle();
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->fields['heading'] ?? '';
    }

    /**
     * Get the see also references for the record.
     *
     * @return array
     */
    public function getSeeAlso()
    {
        return isset($this->fields['see_also'])
            && is_array($this->fields['see_also'])
            ? $this->fields['see_also'] : [];
    }

    /**
     * Get the use for references for the record.
     *
     * @return array
     */
    public function getUseFor()
    {
        return isset($this->fields['use_for'])
            && is_array($this->fields['use_for'])
            ? $this->fields['use_for'] : [];
    }
}
