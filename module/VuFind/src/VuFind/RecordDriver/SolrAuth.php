<?php
/**
 * Model for Solr authority records.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for Solr authority records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
class SolrAuth extends SolrMarc
{
    /**
     * Used for identifying database records
     *
     * @var string
     */
    protected $resourceSource = 'SolrAuth';

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
        return isset($this->fields['heading']) ? $this->fields['heading'] : '';
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
            ? $this->fields['see_also'] : array();
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
            ? $this->fields['use_for'] : array();
    }
}
