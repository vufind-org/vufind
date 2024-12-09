<?php

/**
 * Blender Search Parameters
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Blender;

use VuFind\Search\Base\Params as BaseParams;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindSearch\ParamBag;

use function array_slice;
use function call_user_func_array;
use function count;
use function func_get_args;
use function in_array;
use function is_callable;

/**
 * Blender Search Parameters
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * Search params for backends
     *
     * @var \VuFind\Search\Base\Params[]
     */
    protected $searchParams;

    /**
     * Blender configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $blenderConfig;

    /**
     * Blender mappings
     *
     * @var array
     */
    protected $mappings;

    /**
     * Current filters not supported by a backend
     *
     * @var array
     */
    protected $unsupportedFilters = [];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options       Options to use
     * @param \VuFind\Config\PluginManager $configLoader  Config loader
     * @param HierarchicalFacetHelper      $facetHelper   Hierarchical facet helper
     * @param array                        $searchParams  Search params for backends
     * @param \Laminas\Config\Config       $blenderConfig Blender configuration
     * @param array                        $mappings      Blender mappings
     */
    public function __construct(
        \VuFind\Search\Base\Options $options,
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper,
        array $searchParams,
        \Laminas\Config\Config $blenderConfig,
        array $mappings
    ) {
        // Assign these first; they are needed during parent's construct:
        $this->searchParams = $searchParams;
        $this->blenderConfig = $blenderConfig;
        $this->mappings = $mappings;

        parent::__construct(
            $options,
            $configLoader,
            $facetHelper
        );
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
        $this->unsupportedFilters = [];

        // First do a basic init without filters, facets etc. that are processed via
        // methods called by parent's initFromRequest:
        $filteredParams = [
            'lookfor',
            'type',
            'sort',
            'filter',
            'hiddenFilters',
            'daterange',
        ];
        foreach ($this->searchParams as $params) {
            $translatedRequest = clone $request;
            foreach (array_keys($translatedRequest->getArrayCopy()) as $key) {
                // Check for filtered param or advanced search types:
                if (in_array($key, $filteredParams) || preg_match('/^type\d+$/', $key)) {
                    $translatedRequest->offsetUnset($key);
                }
            }
            $params->initFromRequest($translatedRequest);
        }
        parent::initFromRequest($request);
    }

    /**
     * Initialize the object's search settings from a request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSearch($request)
    {
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            // Clone request to avoid tampering the original one:
            $translatedRequest = clone $request;
            // Map basic search type:
            if ($type = $translatedRequest->get('type')) {
                $translatedRequest->set(
                    'type',
                    $this->translateSearchType($type, $backendId)
                );
            }
            // Map advanced search types:
            $i = 0;
            while ($types = $translatedRequest->get("type$i")) {
                $translatedRequest->set(
                    "type$i",
                    array_map(
                        function ($type) use ($backendId) {
                            return $this->translateSearchType($type, $backendId);
                        },
                        (array)$types
                    )
                );
                ++$i;
            }
            $params->initSearch($translatedRequest);
        }
        parent::initSearch($request);
    }

    /**
     * Set a basic search query:
     *
     * @param string $lookfor The search query
     * @param string $handler The search handler (null for default)
     *
     * @return void
     */
    public function setBasicSearch($lookfor, $handler = null)
    {
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            $params->setBasicSearch(
                $lookfor,
                $handler
                    ? $this->translateSearchType($handler, $backendId) : $handler
            );
        }
        parent::setBasicSearch($lookfor, $handler);
    }

    /**
     * Get the value for which type of sorting to use
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSort($request)
    {
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            // Clone request to avoid tampering the original one:
            $translatedRequest = clone $request;
            // Map sort:
            if ($sort = $translatedRequest->get('sort')) {
                $translatedRequest->set(
                    'sort',
                    $this->translateSort($sort, $backendId)
                );
            }
            $params->initSort($translatedRequest);
        }
        parent::initSort($request);
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
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            $params->setSort(
                $sort ? $this->translateSort($sort, $backendId) : $sort,
                $force
            );
        }
        parent::setSort($sort, $force);
    }

    /**
     * Take a filter string and add it into the protected
     *   array checking for duplicates.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addFilter($newFilter)
    {
        parent::addFilter($newFilter);
        if ($this->isBlenderFilter($newFilter)) {
            return;
        }
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFilter($newFilter, $backendId)) {
                foreach ($translated as $current) {
                    if (null !== $current) {
                        $params->addFilter($current);
                    }
                }
            } else {
                // Add the filter to the list of unsupported filters:
                $this->unsupportedFilters[$backendId][]
                    = $this->parseFilter($newFilter);
            }
        }
    }

    /**
     * Take a filter string and add it into the protected hidden filters
     *   array checking for duplicates.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addHiddenFilter($newFilter)
    {
        parent::addHiddenFilter($newFilter);
        if ($this->isBlenderFilter($newFilter)) {
            return;
        }
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFilter($newFilter, $backendId)) {
                foreach ($translated as $current) {
                    if (null !== $current) {
                        $params->addHiddenFilter($current);
                    }
                }
            } else {
                // Add the filter to the list of unsupported filters:
                $this->unsupportedFilters[$backendId][]
                    = $this->parseFilter($newFilter);
            }
        }
    }

    /**
     * Remove a filter from the list.
     *
     * @param string $oldFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function removeFilter($oldFilter)
    {
        parent::removeFilter($oldFilter);
        if ($this->isBlenderFilter($oldFilter)) {
            return;
        }

        // Update list of unsupported filters:
        if ($this->unsupportedFilters) {
            $parsed = $this->parseFilter($oldFilter);
            foreach ($this->unsupportedFilters as $backendId => $filters) {
                $updatedFilters = $filters;
                foreach ($filters as $key => $filter) {
                    if ($parsed === $filter) {
                        unset($updatedFilters[$key]);
                    }
                }
                $this->unsupportedFilters[$backendId] = $updatedFilters;
            }
        }

        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFilter($oldFilter, $backendId)) {
                foreach ($translated as $current) {
                    $params->removeFilter($current);
                }
            }
        }
    }

    /**
     * Remove all filters from the list.
     *
     * @param string $field Name of field to remove filters from (null to remove
     * all filters from all fields)
     *
     * @return void
     */
    public function removeAllFilters($field = null)
    {
        $this->unsupportedFilters = [];
        if (null === $field) {
            $this->proxyMethod(__FUNCTION__, func_get_args());
            return;
        }

        parent::removeAllFilters($field);
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFacetName($field, $backendId)) {
                $params->removeAllFilters($translated);
            }
        }
    }

    /**
     * Add a field to facet on.
     *
     * @param string $newField Field name
     * @param string $newAlias Optional on-screen display label
     * @param bool   $ored     Should we treat this as an ORed facet?
     *
     * @return void
     */
    public function addFacet($newField, $newAlias = null, $ored = false)
    {
        parent::addFacet($newField, $newAlias, $ored);
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFacetName($newField, $backendId)) {
                $params->addFacet($translated, $newAlias, $ored);
            }
        }
    }

    /**
     * Add a checkbox facet. When the checkbox is checked, the specified filter
     * will be applied to the search. When the checkbox is not checked, no filter
     * will be applied.
     *
     * @param string $filter  [field]:[value] pair to associate with checkbox
     * @param string $desc    Description to associate with the checkbox
     * @param bool   $dynamic Is this being added dynamically (true) or in response
     * to a user configuration (false)?
     *
     * @return void
     */
    public function addCheckboxFacet($filter, $desc, $dynamic = false)
    {
        parent::addCheckboxFacet($filter, $desc, $dynamic);
        if ($this->isBlenderFilter($filter)) {
            return;
        }
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFilter($filter, $backendId)) {
                foreach ($translated as $current) {
                    if (null !== $current) {
                        $params->addCheckboxFacet($current, $desc, $dynamic);
                    }
                }
            }
        }
    }

    /**
     * Reset the current facet configuration.
     *
     * @return void
     */
    public function resetFacetConfig()
    {
        $this->proxyMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters(): ParamBag
    {
        $result = parent::getBackendParameters();
        foreach ($this->unsupportedFilters as $backendId => $filters) {
            if ($filters) {
                $result->add('fq', "-blender_backend:\"$backendId\"");
            }
        }
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if (!is_callable([$params, 'getBackendParameters'])) {
                throw new \Exception(
                    "Backend $backendId missing support for getBackendParameters"
                );
            }

            // Clone params so that adding any default filters does not affect the
            // original instance:
            $params = clone $params;
            $this->addDefaultFilters($params, $backendId);

            $result->set(
                "query_$backendId",
                $params->getQuery()
            );
            $result->set(
                "params_$backendId",
                $params->getBackendParameters()
            );
        }
        return $result;
    }

    /**
     * Add default filters to the given params
     *
     * @param BaseParams $params    Params
     * @param string     $backendId Backend ID
     *
     * @return void
     */
    protected function addDefaultFilters(BaseParams $params, string $backendId): void
    {
        foreach ($this->mappings['Facets']['Fields'] ?? [] as $fieldConfig) {
            $mappings = $fieldConfig['Mappings'][$backendId] ?? [];
            $defaultValue = $mappings['DefaultValue'] ?? null;
            if (null !== $defaultValue) {
                $translatedField = $mappings['Field'];
                $filterList = $params->getFilterList();
                $found = false;
                foreach ($filterList as $filters) {
                    foreach ($filters as $filter) {
                        if ($filter['field'] === $translatedField) {
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) {
                    $params->addFilter("$translatedField:$defaultValue");
                }
            }
        }
    }

    /**
     * Proxy a method call to parent class and all backend params classes
     *
     * @param string $method Method
     * @param array  $params Method parameters
     *
     * @return mixed
     */
    protected function proxyMethod(string $method, array $params)
    {
        $result = call_user_func_array(parent::class . "::$method", $params);
        foreach ($this->searchParams as $searchParams) {
            $result = call_user_func_array([$searchParams, $method], $params);
        }
        return $result;
    }

    /**
     * Translate a facet field name
     *
     * @param string $field     Facet field
     * @param string $backendId Backend ID
     *
     * @return string
     */
    protected function translateFacetName(string $field, string $backendId): string
    {
        $fieldConfig = $this->mappings['Facets']['Fields'][$field] ?? [];
        return $fieldConfig['Mappings'][$backendId]['Field'] ?? '';
    }

    /**
     * Check if the filter is a special Blender filter
     *
     * @param string $filter Filter
     *
     * @return bool
     */
    protected function isBlenderFilter(string $filter): bool
    {
        [, $field] = $this->parseFilterAndPrefix($filter);
        return 'blender_backend' === $field;
    }

    /**
     * Translate a filter
     *
     * @param string $filter    Filter
     * @param string $backendId Backend ID
     *
     * @return array
     */
    protected function translateFilter(string $filter, string $backendId): array
    {
        [$prefix, $field, $value] = $this->parseFilterAndPrefix($filter);

        $fieldConfig = $this->mappings['Facets']['Fields'][$field] ?? [];
        if ($ignore = $fieldConfig['Mappings'][$backendId]['Ignore'] ?? '') {
            if (true === $ignore || in_array($value, (array)$ignore)) {
                return [null];
            }
        }
        $mappings = $fieldConfig['Mappings'][$backendId] ?? [];
        $translatedField = $mappings['Field'] ?? '';
        if (!$mappings || !$translatedField) {
            // Facet not supported by the backend
            return [];
        }

        // Map filter value
        $facetType = $fieldConfig['Type'] ?? 'normal';
        if ('boolean' === $facetType) {
            $value = (bool)$value;
        }
        $resultValues = [];
        foreach ($mappings['Values'] ?? [$value => $value] as $k => $v) {
            if ('boolean' === $facetType) {
                $v = (bool)$v;
            }
            if ($value === $v) {
                $resultValues[] = $k;
            }
        }
        if ($mappings['Hierarchical'] ?? false) {
            $resultValues = $this->addLowerLevelHierarchicalFilterValues(
                $value,
                $resultValues,
                $mappings['Values'] ?? []
            );
        }

        // If the result is more than one value, convert an AND search to OR:
        if ('' === $prefix && count($resultValues) > 1) {
            $prefix = '~';
        }

        $result = [];
        foreach ($resultValues as $value) {
            $result[] = $prefix . $translatedField . ':' . $value;
        }

        return $result;
    }

    /**
     * Handle any lower level mappings when translating hierarchical facets.
     *
     * This ensures that selecting a facet value higher in a hierarchy than the
     * mapped value still adds the correct filter.
     * Example:
     * - Backend's value 'journal' is mapped to hierarchical value
     * '1/Journal/eJournal/'.
     * - When user selects the top level facet '0/Journal/', it needs to be
     * reflected as 'journal' in the backend.
     *
     * @param mixed $value        Filter value
     * @param array $resultValues Current resulting filter values
     * @param array $mappings     Value mappings
     *
     * @return array Updated filter values
     */
    protected function addLowerLevelHierarchicalFilterValues(
        $value,
        array $resultValues,
        array $mappings
    ): array {
        $levelOffset = -1;
        do {
            $levelGood = false;
            foreach ($mappings as $k => $v) {
                $parts = explode('/', $v);
                $partCount = count($parts);
                if ($parts[0] <= 0 || $partCount <= 2) {
                    continue;
                }
                $level = $parts[0] + $levelOffset;
                if ($level < 0) {
                    continue;
                }
                $levelGood = true;
                $levelValue = $level . '/'
                    . implode(
                        '/',
                        array_slice($parts, 1, $level + 1)
                    ) . '/';
                if ($value === $levelValue) {
                    $resultValues[] = $k;
                }
            }
            --$levelOffset;
        } while ($levelGood);

        return $resultValues;
    }

    /**
     * Translate a search type
     *
     * @param string $type      Search type
     * @param string $backendId Backend ID
     *
     * @return string
     */
    protected function translateSearchType(string $type, string $backendId): string
    {
        $mappings = $this->mappings['Search']['Fields'][$type]['Mappings'] ?? [];
        return $mappings[$backendId] ?? '';
    }

    /**
     * Translate a sort option
     *
     * @param string $sort      Sort option
     * @param string $backendId Backend ID
     *
     * @return string
     */
    protected function translateSort(string $sort, string $backendId): string
    {
        $mappings = $this->mappings['Sorting']['Fields'][$sort]['Mappings'] ?? [];
        return $mappings[$backendId] ?? '';
    }
}
