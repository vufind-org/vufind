<?php
/**
 * Model for MARC authority records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for MARC authority records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrAuthMarc extends SolrAuthDefault
{
    use MarcReaderTrait;
    use MarcAdvancedTrait;

    /**
     * Get a raw LCCN (not normalized).  Returns false if none available.
     *
     * @return string|bool
     */
    public function getRawLCCN()
    {
        $lccn = $this->getFirstFieldValue('010');
        if (!empty($lccn)) {
            return $lccn;
        }
        $lccns = $this->getFieldArray('700', ['0']);
        foreach ($lccns as $lccn) {
            if (substr($lccn, 0, '5') == '(DLC)') {
                return substr($lccn, 5);
            }
        }
        return false;
    }
}
