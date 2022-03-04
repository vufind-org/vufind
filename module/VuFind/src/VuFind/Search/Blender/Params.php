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

use Laminas\Stdlib\Parameters;
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
        parent::__construct(
            $options,
            $configLoader,
            $facetHelper
        );

        $this->searchParams = $searchParams;
        $this->blenderConfig = $blenderConfig;
        $this->mappings = $mappings;
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
        parent::initFromRequest($request);
        foreach ($this->searchParams as $params) {
            $params->initFromRequest(
                $this->translateRequest(
                    $request,
                    $params->getOptions()->getSearchClassId()
                )
            );
        }
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters(): ParamBag
    {
        $result = parent::getBackendParameters();
        foreach ($this->searchParams as $params) {
            if (!is_callable([$params, 'getBackendParameters'])) {
                throw new \Exception(
                    'Backend ' . $params->getBackendId()
                    . ' missing support for getBackendParameters'
                );
            }
            $result->set(
                'params_' . $params->getOptions()->getSearchClassId(),
                $params->getBackendParameters()
            );
        }
        return $result;
    }

    /**
     * Translate a request for a backend
     *
     * @param Parameters $request   Parameter object representing user request.
     * @param string     $backendId Backend identifier
     *
     * @return Parameters
     */
    protected function translateRequest(
        Parameters $request,
        string $backendId
    ): Parameters {
        // Clone request to avoid tampering with the original one:
        $request = clone $request;
        $facets = $this->mappings['Facets'] ?? [];
        $filters = $request->get('filter');
        if (!empty($filters)) {
            $newFilters = [];
            foreach ((array)$filters as $filter) {
                [$field, $value] = $this->parseFilter($filter);
                // Ignore special blender_backend filters here:
                if ('blender_backend' === $field) {
                    continue;
                }
                $prefix = '';
                if (substr($field, 0, 1) === '~') {
                    $prefix = '~';
                    $field = substr($field, 1);
                }
                $values = [$value];

                $resultValues = $this->translateFilter($field, $value, $backendId);
                if ($resultValues) {
                    $values = $resultValues;
                }
                $field = $facets['Fields'][$field]['Mappings'][$backendId]['Field']
                    ?? '';
                if (!$field) {
                    continue;
                }

                // EDS special case:
                if ('EDS' === $backendId) {
                    array_map(
                        [
                            '\VuFindSearch\Backend\EDS\SearchRequestModel',
                            'escapeSpecialCharacters'
                        ],
                        $values
                    );
                }
                foreach ($values as $value) {
                    $newFilters[] = $prefix . $field . ':"' . $value . '"';
                }
            }
            $request->set('filter', $newFilters);
        }

        // Map search type:
        if ($type = $request->get('type')) {
            $request->set(
                'type',
                $this->mappings['Search']['Fields'][$type][$backendId] ?? ''
            );
        }

        // Map sort option:
        if ($sort = $request->get('sort')) {
            $request->set(
                'sort',
                $this->mappings['Sorting']['Fields'][$sort][$backendId] ?? ''
            );
        }

        return $request;
    }

    /**
     * Translate a facet filter
     *
     * @param string $field     Filter field
     * @param string $value     Filter value
     * @param string $backendId Backend ID
     *
     * @return array
     */
    protected function translateFilter(
        string $field,
        string $value,
        string $backendId
    ): array {
        $fieldConfig = $this->mappings['Facets']['Fields'][$field] ?? [];
        $mappings = $fieldConfig['Mappings'][$backendId] ?? [];
        if (!$mappings || empty($mappings['Field'])) {
            // Facet not supported by the backend
            return [];
        }

        // Map facet value
        $facetType = $fieldConfig['Type'] ?? 'normal';
        if ('boolean' === $facetType) {
            $value = (bool)$value;
        }
        $resultValues = [];
        foreach ($mappings['Values'] ?? [] as $k => $v) {
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

        return $resultValues;
    }
}
