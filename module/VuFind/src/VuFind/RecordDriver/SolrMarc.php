<?php

/**
 * Model for MARC records in Solr.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use function array_key_exists;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrMarc extends SolrDefault
{
    use Feature\IlsAwareTrait {
        Feature\IlsAwareTrait::getURLs as getIlsURLs;
    }
    use Feature\MarcReaderTrait;
    use Feature\MarcAdvancedTrait {
        Feature\MarcAdvancedTrait::getURLs as getMarcURLs;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $marcUrls = $this->getMarcURLs();
        $ilsUrls = $this->getIlsURLs();
        if ($this->mainConfig['Catalog']['holdings_dedup_urls'] ?? false) {
            // Dedup by URL. Prefer URLs from the ILS, and preserve its sort order.
            $combinedUrls = [];
            foreach ($ilsUrls as $url) {
                $combinedUrls[$url['url']] = $url;
            }
            foreach ($marcUrls as $url) {
                if (!array_key_exists($url['url'], $combinedUrls)) {
                    $combinedUrls[$url['url']] = $url;
                }
            }
            return array_values($combinedUrls);
        } else {
            return array_merge($marcUrls, $ilsUrls);
        }    
    }
}
