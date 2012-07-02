<?php
/**
 * Summon Search API Interface (query model - VuFind implementation)
 *
 * PHP version 5
 *
 * Copyright (C) Serials Solutions 2011.
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
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
namespace VuFind\Connection\Summon;

/**
 * Summon REST API Interface (query model - VuFind implementation)
 *
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class Query extends \SerialsSolutions_Summon_Query
{
    protected $config;

    /**
     * Constructor
     *
     * Sets up the Summon API Client
     *
     * @param string $query   Search query
     * @param array  $options Other options to set (associative array)
     */
    public function __construct($query = null, $options = array())
    {
        parent::__construct($query, $options);
        $this->config = VF_Config_Reader::getConfig('Summon');
    }

    /**
     * Set up facets based on VuFind settings.
     *
     * @param array $facets Facet settings
     *
     * @return void
     */
    public function initFacets($facets)
    {
        $this->facets = array();
        foreach ($facets as $facet) {
            // See if parameters are included as part of the facet name;
            // if not, override them with defaults.
            $parts = explode(',', $facet);
            $facetName = $parts[0];
            $facetMode = isset($parts[1]) ? $parts[1] : 'and';
            $facetPage = isset($parts[2]) ? $parts[2] : 1;
            if (isset($parts[3])) {
                $facetLimit = $parts[3];
            } else {
                $facetLimit = isset($this->config->Facet_Settings->facet_limit)
                    ? $this->config->Facet_Settings->facet_limit : 30;
            }
            $facetParams = "{$facetMode},{$facetPage},{$facetLimit}";
            $this->facets[] = "{$facetName},{$facetParams}";
        }
    }

    /**
     * Set up filters based on VuFind settings.
     *
     * @param array $filterList Filter settings
     *
     * @return void
     */
    public function initFilters($filterList)
    {
        // Which filters should be applied to our query?
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $safeValue = self::escapeParam($filt['value']);
                    // Special case -- "holdings only" is a separate parameter from
                    // other facets.
                    if ($filt['field'] == 'holdingsOnly') {
                        $this->setHoldings(strtolower(trim($safeValue)) == 'true');
                    } else if ($filt['field'] == 'excludeNewspapers') {
                        // Special case -- support a checkbox for excluding
                        // newspapers:
                        $this->addFilter("ContentType,Newspaper Article,true");
                    } else if ($range = VF_Solr_Utils::parseRange($filt['value'])) {
                        // Special case -- range query (translate [x TO y] syntax):
                        $from = self::escapeParam($range['from']);
                        $to = self::escapeParam($range['to']);
                        $this->addRangeFilter("{$filt['field']},{$from}:{$to}");
                    } else {
                        // Standard case:
                        $this->addFilter("{$filt['field']},{$safeValue}");
                    }
                }
            }
        }
    }
}
