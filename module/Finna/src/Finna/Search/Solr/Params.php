<?php
/**
 * Solr Search Parameters
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @category VuFind
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Solr;
use VuFind\Solr\Utils;

/**
 * Solr Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Solr\Params
{
    use \Finna\Search\FinnaParams;

    /**
     * Search handler for browse actions
     *
     * @var string
     */
    protected $browseHandler;

    /**
     * Date converter
     *
     * @var \Vufind\Date\Converter
     */
    protected $dateConverter;

    /**
     * New items facet configuration
     *
     * @var array
     */
    protected $newItemsFacets = [];

    /**
     * Query debug flag
     *
     * @var bool
     */
    protected $debugQuery = false;

    // Date range index field (VuFind1)
    const SPATIAL_DATERANGE_FIELD_VF1 = 'search_sdaterange_mv';
    const SPATIAL_DATERANGE_FIELD_TYPE_VF1 = 'search_sdaterange_mvtype';

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options       Options to use
     * @param \VuFind\Config\PluginManager $configLoader  Config loader
     * @param \VuFind\Date\Converter       $dateConverter Date converter
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader,
        \VuFind\Date\Converter $dateConverter
    ) {
        parent::__construct($options, $configLoader);

        $this->dateConverter = $dateConverter;
        $config = $configLoader->get($options->getFacetsIni());

        // New items facets
        if (isset($config->SpecialFacets->newItems)) {
            $this->newItemsFacets = $config->SpecialFacets->newItems->toArray();
        }
    }

    /**
     * Support method for initSearch() -- handle basic settings.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        if ($handler = $request->get('browseHandler')) {
            $this->setBrowseHandler($handler);
        }
        return parent::initBasicSearch($request);
    }

    /**
     * Return the selected search handler (null for complex searches which have no
     * single handler)
     *
     * @return string|null
     */
    public function getSearchHandler()
    {
        return $this->browseHandler ?: parent::getSearchHandler();
    }

    /**
     * Set search handler for browse actions
     *
     * @param string $handler Hander
     *
     * @return string|null
     */
    public function setBrowseHandler($handler)
    {
        return $this->browseHandler = $handler;
    }

    /**
     * Does the object already contain the specified filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return bool
     */
    public function addFilter($filter)
    {
        // Extract field and value from URL string:
        list($field, $value) = $this->parseFilter($filter);

        if ($field == $this->getDateRangeSearchField()) {
            // Date range filters are processed
            // separately (see initSpatialDateRangeFilter)
            return;
        }
        parent::addFilter($filter);
    }

    /**
     * Return current date range filter.
     *
     * @return mixed false|array Filter
     */
    public function getDateRangeFilter()
    {
        $filterList = $this->getFilterList();
        foreach ($filterList as $facet => $filters) {
            foreach ($filters as $filter) {
                if ($this->isDateRangeFilter($filter['field'])) {
                    return $filter;
                }
            }
        }
        return false;
    }

    /**
     * Return the current filters as an array of strings ['field:filter']
     *
     * @return array $filterQuery
     */
    public function getFilterSettings()
    {
        $result = parent::getFilterSettings();

        // Special processing for date range filters
        $dateRangeField = $this->getDateRangeSearchField();
        if ($dateRangeField) {
            foreach ($result as &$filter) {
                $dateRange = strncmp(
                    $filter, "$dateRangeField:", strlen($dateRangeField) + 1
                ) == 0;
                if ($dateRange) {
                    list($field, $value) = $this->parseFilter($filter);
                    list($op, $range) = explode('|', $value);
                    $op = $op == 'within' ? 'Within' : 'Intersects';
                    $filter = "{!field f=$dateRangeField op=$op}$range";
                }
            }
        }
        return $result;
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $result = parent::getBackendParameters();

        if ($this->debugQuery) {
            $result->add('debugQuery', 'true');
        }

        return $result;
    }

    /**
     * Return current facet configurations.
     * Add checkbox facets to list.
     *
     * @return array $facetSet
     */
    public function getFacetSettings()
    {
        $facetSet = parent::getFacetSettings();
        if (!empty($this->checkboxFacets)) {
            foreach (array_keys($this->checkboxFacets) as $facetField) {
                $facetField = '{!ex=' . $facetField . '_filter}' . $facetField;
                $facetSet['field'][] = $facetField;
            }
        }
        return $facetSet;
    }

    /**
     * Pull the search parameters
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initFromRequest($request)
    {
        // Check for advanced search with missing join parameter from VuFind1:
        if (null === $request->get('lookfor')) {
            if (null === $request->get('join')) {
                $request->set('join', 'AND');
            }
        }

        // Check for VuFind1 orfilters and convert them:
        if ($orFilters = $request->get('orfilter')) {
            $filters = $request->get('filter', []);
            foreach ($orFilters as $filter) {
                $filters[] = "~$filter";
            }
            $request->set('filter', $filters);
            $request->set('orfilter', null);
        }

        parent::initFromRequest($request);

        $this->setDebugQuery($request->get('debugSolrQuery', false));
    }

    /**
     * Initialize coordinate filter (coordinates, VuFind1)
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initCoordinateFilter($request)
    {
        $coordinates = $request->get('coordinates');
        if (null === $coordinates) {
            return;
        }

        // Convert simple coordinates to a polygon
        if (preg_match(
            '/^([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)$/',
            $coordinates,
            $matches
        )) {
            list(, $minX, $minY, $maxX, $maxY) = $matches;
            $coordinates = "POLYGON(($minX $maxY,$maxX $maxY,$maxX $minY"
                . ",$minX $minY,$minX $maxY))";
        }
        $this->addFilter(
            '{!score=none}location_geo:"Intersects('
            . str_replace('"', '\"', $coordinates) . ')"'
        );
    }

    /**
     * Initialize date range filter (search_daterange_mv)
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initSpatialDateRangeFilter($request)
    {
        $dateRangeField = $this->getDateRangeSearchField();
        if (!$dateRangeField) {
            return;
        }
        $type = $request->get("{$dateRangeField}_type");
        if (!$type) {
            // VuFind 1
            $type = $request->get(self::SPATIAL_DATERANGE_FIELD_TYPE_VF1);
        }
        if (!$type) {
            $type = 'overlap';
        }

        $from = $to = null;
        $filters = $this->getFilters();
        $found = false;

        // Date range filter
        if ($reqFilters = $request->get('filter')) {
            foreach ($reqFilters as $f) {
                list($field, $value) = $this->parseFilter($f);
                if ($field == $dateRangeField
                    || $field == self::SPATIAL_DATERANGE_FIELD_VF1
                ) {
                    if ($range = $this->parseDateRangeFilter($f)) {
                        $from = $range['from'];
                        $to = $range['to'];
                        if (isset($range['type'])) {
                            $type = $range['type'];
                        }
                        $found = true;
                        break;
                    }
                }
            }
        }

        // Uninitialized VuFind1 date range query
        if (!$found && $request->get('sdaterange')) {
            // Search for VuFind1 search_sdaterange_mvfrom, search_sdaterange_mvto
            $from = $request->get('search_sdaterange_mvfrom');
            $to = $request->get('search_sdaterange_mvto');
            if (!empty($from) || !empty($to)) {
                if (empty($from)) {
                    $from = -9999;
                }
                if (empty($to)) {
                    $to = 9999;
                }
                $found = true;
            }
        }

        if (!$found) {
            return;
        }

        // Add filter. The final Solr filter is constructed in getFilterSettings.
        $filter = "$dateRangeField:$type|[$from TO $to]";
        parent::addFilter($filter);
    }

    /**
     * Get query debug flag status
     *
     * @return bool
     */
    public function getDebugQuery()
    {
        return $this->debugQuery;
    }

    /**
     * Enable or disable query debugging
     *
     * @param bool $value Whether to enable debugging
     *
     * @return void
     */
    public function setDebugQuery($value)
    {
        $this->debugQuery = $value;
    }

    /**
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        if (!in_array($field, $this->newItemsFacets)
            || !($range = Utils::parseRange($value))
        ) {
            $result = parent::formatFilterListEntry(
                $field, $value, $operator, $translate
            );
            return $this->formatDateRangeFilterListEntry($result, $field, $value);
        }

        $domain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
        list($from, $fromDate) = $this->formatNewItemsDateForDisplay(
            $range['from'], $domain
        );
        list($to, $toDate) = $this->formatNewItemsDateForDisplay(
            $range['to'], $domain
        );
        $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
        if ($fromDate && $toDate) {
            $displayText = $from ? "$from $ndash" : $ndash;
            $displayText .= $to ? " $to" : '';
        } else {
            $displayText = $from;
            $displayText .= $to ? " $ndash $to" : '';
        }

        return compact('value', 'displayText', 'field', 'operator');
    }

    /**
     * Format a Solr date for display
     *
     * @param string $date   Date
     * @param string $domain Translation domain
     *
     * @return string
     */
    protected function formatNewItemsDateForDisplay($date, $domain)
    {
        if ($date == '' || $date == '*') {
            return ['', true];
        }
        if (preg_match('/^NOW-(\w+)/', $date, $matches)) {
            return [
                $this->translate("$domain::new_items_" . strtolower($matches[1])),
                false
            ];
        }
        $date = substr($date, 0, 10);
        return [
            $this->dateConverter->convertToDisplayDate('Y-m-d', $date),
            true
        ];
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        parent::initFilters($request);
        $this->initSpatialDateRangeFilter($request);
        $this->initNewItemsFilter($request);
        $this->initCoordinateFilter($request);
    }

    /**
     * Initialize new items filter (first_indexed)
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initNewItemsFilter($request)
    {
        // first_indexed filter automatically included, no query param required
        // (compatible with Finna 1 implementation)
        $from = $request->get('first_indexedfrom');
        $from = $this->formatDateForFullDateRange($from);

        if ($from != '*') {
            $rangeFacet
                = $this->buildFullDateRangeFilter('first_indexed', $from, '*');
            $this->addFilter($rangeFacet);
        }
    }
}
