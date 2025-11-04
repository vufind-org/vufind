<?php

/**
 * Solr Search Parameters
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\Solr;

use VuFind\Config\Config;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Solr\Utils;

use function in_array;
use function is_array;
use function strlen;

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
    use \Finna\Search\DateRangeFilterTrait;
    use \Finna\Search\FinnaParams;
    use ParamsSharedTrait;

    /**
     * Maximum facet limit
     *
     * @var int
     */
    public const MAX_FACET_LIMIT = 100;

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

    /**
     * Helper for formatting authority id filter display texts.
     *
     * @var AuthorityHelper
     */
    protected $authorityHelper = null;

    /**
     * Facet filters.
     *
     * @var array
     */
    protected $facetFilters = [];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options $options         Options to use
     * @param ConfigManagerInterface      $configManager   Config manager
     * @param HierarchicalFacetHelper     $facetHelper     Hierarchical
     *                                                     facet helper
     * @param AuthorityHelper             $authorityHelper Authority helper
     * @param \VuFind\Date\Converter      $dateConverter   Date converter
     */
    public function __construct(
        $options,
        ConfigManagerInterface $configManager,
        HierarchicalFacetHelper $facetHelper,
        AuthorityHelper $authorityHelper,
        \VuFind\Date\Converter $dateConverter
    ) {
        parent::__construct($options, $configManager, $facetHelper);

        $this->dateConverter = $dateConverter;
        $this->authorityHelper = $authorityHelper;

        // New items facets
        if ($newItems = $this->facetConfig['SpecialFacets']['newItems'] ?? null) {
            $this->newItemsFacets = $newItems;
        }
    }

    /**
     * Restore settings from a minified object found in the database.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function deminify($minified)
    {
        parent::deminify($minified);
        $dateRangeField = $this->getDateRangeSearchField();
        if (!$dateRangeField) {
            return;
        }
        // Convert any VuFind 1 spatial date range filter
        if (isset($this->filterList[$this->spatialDateRangeFieldVF1])) {
            $dateRangeFilters = $this->filterList[$this->spatialDateRangeFieldVF1];
            unset($this->filterList[$this->spatialDateRangeFieldVF1]);

            foreach ($dateRangeFilters as $filter) {
                if ($range = $this->parseDateRangeFilter($filter)) {
                    $from = $range['from'];
                    $to = $range['to'];
                    $type = $range['type'] ?? 'overlap';
                    $filter = "$dateRangeField:$type|[$from TO $to]";
                    parent::addFilter($filter);
                }
            }
        }
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
                false,
            ];
        }
        $date = substr($date, 0, 10);
        return [
            $this->dateConverter->convertToDisplayDate('Y-m-d', $date),
            true,
        ];
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

        // Restore original sort if we have geographic filters
        $sort = $this->normalizeSort($this->getSort() ?? '');
        $newSort = $result->get('sort');
        if ($newSort && $newSort[0] != $sort) {
            $filters = $result->get('fq');
            if (null !== $filters) {
                foreach ($filters as $filter) {
                    if (strncmp($filter, '{!geofilt ', 10) == 0) {
                        $newSort[0] = $this->normalizeSort($sort);
                        $result->set('sort', $newSort);
                        break;
                    }
                }
            }
        }

        foreach ($this->facetFilters as $filter => $value) {
            $result->add($filter, $value);
        }

        return $result;
    }

    /**
     * Pull the search parameters
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initFromRequest($request)
    {
        // Check for advanced search from VuFind1 missing join and/or bool parameter:
        if (null === $request->get('lookfor')) {
            if (null === $request->get('join')) {
                $request->set('join', 'AND');
            }
            $bool0 = $request->get('bool0');
            if (!is_array($bool0) || empty(array_filter($bool0))) {
                $request->set('bool0', ['AND']);
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
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
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
        $simple = preg_match(
            '/^([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)$/',
            $coordinates,
            $matches
        );
        if ($simple) {
            [, $minX, $minY, $maxX, $maxY] = $matches;
            $coordinates = "POLYGON(($minX $maxY,$maxX $maxY,$maxX $minY"
                . ",$minX $minY,$minX $maxY))";
        }
        $this->addFilter(
            '{!score=none}location_geo:"Intersects('
            . str_replace('"', '\"', $coordinates) . ')"'
        );
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
     * Remove all hidden filters
     *
     * @return void
     */
    public function clearHiddenFilters()
    {
        $this->hiddenFilters = [];
    }

    /**
     * Get current limit for hierarchical facets
     *
     * @return int
     */
    public function getHierarchicalFacetLimit()
    {
        return $this->hierarchicalFacetLimit;
    }

    /**
     * Set limit for hierarchical facets
     *
     * @param int $limit New limit
     *
     * @return void
     */
    public function setHierarchicalFacetLimit($limit)
    {
        $this->hierarchicalFacetLimit = $limit;
    }

    /**
     * Filter facets by prefix.
     *
     * @param string $field Facet field
     * @param string $value Facet value
     *
     * @return void
     */
    public function addFacetFilter($field, $value)
    {
        $this->facetFilters["f.{$field}.facet.prefix"] = $value;
    }

    /**
     * Return active author id filters.
     *
     * @param boolean $includeRole Return role with author id
     *
     * @return mixed null|array
     */
    public function getAuthorIdFilter($includeRole = false)
    {
        $result = [];
        foreach ($this->getFilterList() as $key => $val) {
            foreach ($val as $filterItem) {
                $filter = $filterItem['value'] ?? null;
                if (!$filter) {
                    continue;
                }
                $field = $filterItem['field'];
                if (
                    in_array(
                        $field,
                        [AuthorityHelper::AUTHOR2_ID_FACET,
                        AuthorityHelper::TOPIC_ID_FACET]
                    )
                ) {
                    // Author id filter
                    $result[] = $filter;
                } elseif ($field === AuthorityHelper::AUTHOR_ID_ROLE_FACET) {
                    // Author id-role filter
                    if ($includeRole) {
                        $result[] = $filter;
                    } else {
                        [$id, $role]
                            = $this->authorityHelper->extractRole($filter);
                        $result[] = $id;
                    }
                }
            }
        }
        return !empty($result) ? $result : null;
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
        if (
            !in_array($field, $this->newItemsFacets)
            || !($range = Utils::parseRange($value))
        ) {
            if (
                $translate
                && in_array($field, $this->getOptions()->getHierarchicalFacets())
            ) {
                return $this->translateHierarchicalFacetFilter(
                    $field,
                    $value,
                    $operator
                );
            }
            $result = parent::formatFilterListEntry(
                $field,
                $value,
                $operator,
                $translate
            );

            if ($this->isDateRangeFilter($field)) {
                return $this->formatDateRangeFilterListEntry(
                    $result,
                    $field,
                    $value
                );
            }
            if ($this->isGeographicFilter($field)) {
                return $this->formatGeographicFilterListEntry(
                    $result,
                    $field,
                    $value
                );
            }

            return $this->formatAuthorIdFilterListEntry($result, $field, $value);
        }

        $domain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
        [$from, $fromDate] = $this->formatNewItemsDateForDisplay(
            $range['from'],
            $domain
        );
        [$to, $toDate] = $this->formatNewItemsDateForDisplay(
            $range['to'],
            $domain
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
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field               Facet field name.
     * @param string $value               Facet value.
     * @param string $default             Default field name (null for default behavior).
     * @param bool   $allowCheckboxFacets Should checkbox facet labels be allowed too?
     *
     * @return string Human-readable description of field.
     */
    public function getFacetLabel($field, $value = null, $default = null, $allowCheckboxFacets = true)
    {
        if ($field === AuthorityHelper::AUTHOR2_ID_FACET) {
            return 'authority_id_label';
        }
        if (str_starts_with($field, '{!geofilt ')) {
            return 'Geographical Area';
        }
        return parent::getFacetLabel($field, $value, $default, $allowCheckboxFacets);
    }

    /**
     * Is author id filter active?
     *
     * @return boolean
     */
    public function hasAuthorIdFilter()
    {
        foreach ($this->getFilterList() as $field => $facets) {
            foreach ($facets as $facet) {
                if (
                    in_array(
                        $facet['field'],
                        $this->authorityHelper->getAuthorIdFacets()
                    )
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
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
     * Initialize new items filter (first_indexed and other configured ones)
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initNewItemsFilter($request)
    {
        // first_indexed filter automatically included (compatible with Finna 1 implementation)
        foreach (array_unique([...$this->newItemsFacets, 'first_indexed']) as $field) {
            $queryField = $field . 'from';
            $from = $request->get($queryField, '');
            $from = $this->formatDateForFullDateRange($from);

            if ($from != '*') {
                $this->addFilter($this->buildFullDateRangeFilter($field, $from, '*'));
            }
        }
    }

    /**
     * Initialize facet limit from a Config object.
     *
     * @param ?Config $config Configuration
     *
     * @return void
     */
    protected function initFacetLimitsFromConfig(?Config $config = null)
    {
        parent::initFacetLimitsFromConfig($config);
        $this->constrainFacetLimits();
    }

    /**
     * Constrain facet limits to 1-100 (or -1 for full facet list in advanced
     * search).
     *
     * @return void
     */
    protected function constrainFacetLimits(): void
    {
        if (-1 !== (int)$this->facetLimit) {
            $this->facetLimit
                = max(min((int)$this->facetLimit, static::MAX_FACET_LIMIT), 1);
        }
        foreach ($this->facetLimitByField as &$value) {
            if (-1 !== (int)$value) {
                $value = max(min((int)$value, static::MAX_FACET_LIMIT), 1);
            }
        }
        unset($value);
    }

    /**
     * Set the sorting value (note: sort will be set to default if an illegal
     * or empty value is passed in).
     *
     * @param string $sort  New sort value (null for default)
     * @param bool   $force Set sort value without validating it?
     *
     * @return void
     */
    public function setSort($sort, $force = false)
    {
        // We used to include the tie breaker in all sort options, so strip it out before doing anything else so that
        // any saved searches or links containing it still work properly and display the correct value:
        if ($sort && ($tieBreaker = $this->getOptions()->getSortTieBreaker())) {
            if (str_ends_with($sort, ",$tieBreaker")) {
                $sort = substr($sort, 0, -strlen($tieBreaker) - 1);
            }
        }
        if (!$force) {
            // Check if we need to convert the sort to a currently valid option
            // (it must be a prefix of a currently valid option):
            $validOptions = array_keys($this->getOptions()->getSortOptions());
            if (!empty($sort) && !in_array($sort, $validOptions)) {
                $sortLen = strlen($sort);
                foreach ($validOptions as $valid) {
                    if (
                        strlen($valid) > $sortLen
                        && strncmp($sort, $valid, $sortLen) === 0
                    ) {
                        $sort = $valid;
                        break;
                    }
                }
            }
        }

        parent::setSort($sort, $force);
    }
}
