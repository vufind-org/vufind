<?php
/**
 * Solr Reserves Autocomplete Module
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
 * @package  Autocomplete
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
namespace VuFind\Autocomplete;

/**
 * Solr Reserves Autocomplete Module
 *
 * This class provides suggestions by using the local Solr reserves index.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
class SolrReserves extends Solr
{
    /**
     * Constructor
     *
     * Establishes base settings for making autocomplete suggestions.
     *
     * @param string $params Additional settings from searches.ini.
     */
    public function __construct($params)
    {
        // Use a different default field; otherwise, behave the same as the parent:
        $this->defaultDisplayField = 'course';

        parent::__construct($params);
    }

    /**
     * initSearchObject
     *
     * Initialize the search object used for finding recommendations.
     *
     * @return void
     */
    protected function initSearchObject()
    {
        // Build a new search object:
        $params = new \VuFind\Search\SolrReserves\Params();
        $this->searchObject = new \VuFind\Search\SolrReserves\Results($params);
        $this->searchObject->getOptions()->spellcheckEnabled(false);
    }
}
