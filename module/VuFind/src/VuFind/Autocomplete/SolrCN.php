<?php

/**
 * Solr Call Number Autocomplete Module
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */

namespace VuFind\Autocomplete;

/**
 * Solr Call Number Autocomplete Module
 *
 * This class provides smart call number suggestions by using the local Solr index.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class SolrCN extends Solr
{
    /**
     * Set parameters that affect the behavior of the autocomplete handler.
     * These values normally come from the search configuration file.
     *
     * @param string $params Parameters to set
     *
     * @return void
     */
    public function setConfig($params)
    {
        // Ignore incoming configuration and force CallNumber settings.
        parent::setConfig('CallNumber');
    }

    /**
     * Process the user query to make it suitable for a Solr query.
     *
     * @param string $query   Incoming user query
     * @param array  $options Array of extra parameters
     *
     * @return string        Processed query
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function mungeQuery(string $query, array $options = []): string
    {
        // Modify the query so it makes a nice, truncated autocomplete query:
        $forbidden = [':', '(', ')', '*', '+', '"'];
        $query = str_replace($forbidden, ' ', $query);

        // Assign display fields and sort order based on the query -- if the
        // first character is a number, give Dewey priority; otherwise, give
        // LC priority:
        if (is_numeric(substr(trim($query), 0, 1))) {
            $this->setDisplayField(['dewey-full', 'callnumber-raw']);
            $this->setSortField('dewey-sort,callnumber-sort');
        } else {
            $this->setDisplayField(['callnumber-raw', 'dewey-full']);
            $this->setSortField('callnumber-sort,dewey-sort');
        }

        return $query;
    }
}
