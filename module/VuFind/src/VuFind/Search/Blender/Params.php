<?php
/**
 * Blender Search Parameters
 *
 * PHP version 7
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
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\Blender;

use VuFind\Search\Base\Params as BaseParams;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindSearch\ParamBag;

/**
 * Blender Search Parameters
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * Search params for backends
     *
     * @var \VuFind\Search\Base\Params
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
     * Temporarily disabled backends
     *
     * @var array
     */
    protected $disabledBackends = [];

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
        $mappings
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
        $this->disabledBackends = [];

        // First do a basic init without filters, facets etc. that are processed via
        // methods called by parent's initFromRequest:
        $filteredParams = [
            'lookfor',
            'type',
            'sort',
            'filter',
            'hiddenFilter'
        ];
        foreach ($this->searchParams as $params) {
            $translatedRequest = clone $request;
            foreach (array_keys($translatedRequest->getArrayCopy()) as $key) {
                if (in_array($key, $filteredParams)) {
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
        parent::initSearch($request);
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            // Clone request to avoid tampering the original one:
            $translatedRequest = clone $request;
            // Map search type:
            if ($type = $translatedRequest->get('type')) {
                $translatedRequest->set(
                    'type',
                    $this->mappings['Search']['Fields'][$type][$backendId] ?? ''
                );
            }
            $params->initSearch($translatedRequest);
        }
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
        parent::initSort($request);
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            // Clone request to avoid tampering the original one:
            $translatedRequest = clone $request;
            // Map sort:
            if ($sort = $translatedRequest->get('sort')) {
                $translatedRequest->set(
                    'sort',
                    $this->mappings['Sorting']['Fields'][$sort][$backendId] ?? ''
                );
            }
            $params->initSort($translatedRequest);
        }
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
                    if ('blender_ignore:true' !== $current) {
                        $params->addFilter($current);
                    }
                }
            } else {
                // Disable the backend since it doesn't support the filter:
                $this->disabledBackends[$backendId] = true;
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
                    if ('blender_ignore:true' !== $current) {
                        $params->addHiddenFilter($current);
                    }
                }
            } else {
                // Disable the backend since it doesn't support the filter:
                $this->disabledBackends[$backendId] = true;
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
            if ($translated = $this->translateFacet($newField, $backendId)) {
                $params->addFacet($translated, $newAlias, $ored);
            }
        }
    }

    /**
     * Add a checkbox facet.  When the checkbox is checked, the specified filter
     * will be applied to the search.  When the checkbox is not checked, no filter
     * will be applied.
     *
     * @param string $filter [field]:[value] pair to associate with checkbox
     * @param string $desc   Description to associate with the checkbox
     *
     * @return void
     */
    public function addCheckboxFacet($filter, $desc)
    {
        parent::addCheckboxFacet($filter, $desc);
        if ($this->isBlenderFilter($filter)) {
            return;
        }
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if ($translated = $this->translateFilter($filter, $backendId)) {
                foreach ($translated as $current) {
                    if ('blender_ignore:true' !== $current) {
                        $params->addCheckboxFacet($current, $desc);
                    }
                }
            } else {
                // Disable the backend since it doesn't support the filter:
                $this->disabledBackends[$backendId] = true;
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
        foreach (array_keys($this->disabledBackends) as $backendId) {
            $result->add('fq', "-blender_backend:$backendId");
        }
        foreach ($this->searchParams as $params) {
            $backendId = $params->getSearchClassId();
            if (!is_callable([$params, 'getBackendParameters'])) {
                throw new \Exception(
                    "Backend $backendId missing support for getBackendParameters"
                );
            }

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
        foreach ($this->mappings['Facets']['Fields'] as $field => $fieldConfig) {
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
        $result = call_user_func_array(['parent', $method], $params);
        foreach ($this->searchParams as $searchParams) {
            $result = call_user_func_array([$searchParams, $method], $params);
        }
        return $result;
    }

    /**
     * Translate a facet
     *
     * @param string $field     Facet field
     * @param string $backendId Backend ID
     *
     * @return string
     */
    protected function translateFacet(string $field, string $backendId): string
    {
        $fieldConfig = $this->mappings['Facets']['Fields'][$field] ?? [];
        $mappings = $fieldConfig['Mappings'][$backendId] ?? [];
        return $mappings['Field'] ?? '';
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
        [$field] = $this->parseFilter($filter);
        if (substr($field, 0, 1) === '~') {
            $field = substr($field, 1);
        }
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
        [$field, $value] = $this->parseFilter($filter);
        $prefix = '';
        if (substr($field, 0, 1) === '~') {
            $prefix = '~';
            $field = substr($field, 1);
        }

        $fieldConfig = $this->mappings['Facets']['Fields'][$field] ?? [];
        $translatedField = $fieldConfig['Mappings'][$backendId]['Field'] ?? '';
        if (!$translatedField) {
            return [];
        }

        $mappings = $fieldConfig['Mappings'][$backendId] ?? [];
        if (!$mappings || empty($mappings['Field'])) {
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
        // Check also higher levels when converting hierarchical facets:
        if ('hierarchical' === $facetType) {
            $levelOffset = -1;
            do {
                $levelGood = false;
                foreach ($mappings['Values'] ?? [] as $k => $v) {
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
        }

        // Apply any RegExp mappings:
        foreach ($mappings['RegExp'] ?? [] as $regexp) {
            $search = $regexp['Search'] ?? '';
            $replace = $regexp['Replace'] ?? '';
            if ($search) {
                $resultValues[]
                    = preg_replace("/$search/", $replace, $value);
            }
        }

        $result = [];
        foreach ($resultValues as $value) {
            $result[] = $prefix . $translatedField . ':' . $value;
        }

        return $result;
    }
}
