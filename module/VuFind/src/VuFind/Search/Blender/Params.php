<?php
/**
 * Blender Search Parameters
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
namespace VuFind\Search\Blender;

use VuFind\Search\Solr\HierarchicalFacetHelper;

/**
 * Blender Search Parameters
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
    /**
     * Secondary search params
     *
     * @var \VuFind\Search\Base\Params
     */
    protected $secondaryParams;

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
     * @param \VuFind\Search\Base\Options  $options         Options to use
     * @param \VuFind\Config\PluginManager $configLoader    Config loader
     * @param HierarchicalFacetHelper      $facetHelper     Hierarchical facet helper
     * @param \VuFind\Search\Base\Params   $secondaryParams Secondary search params
     * @param \Laminas\Config\Config       $blenderConfig   Blender configuration
     * @param array                        $mappings        Blender mappings
     */
    public function __construct(
        \VuFind\Search\Base\Options $options,
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper,
        \VuFind\Search\Base\Params $secondaryParams,
        \Laminas\Config\Config $blenderConfig,
        $mappings
    ) {
        parent::__construct(
            $options,
            $configLoader,
            $facetHelper
        );

        $this->secondaryParams = $secondaryParams;
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
        $this->secondaryParams->initFromRequest($this->translateRequest($request));
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return \VuFindSearch\ParamBag
     */
    public function getBackendParameters()
    {
        $params = parent::getBackendParameters();
        if (!is_callable([$this->secondaryParams, 'getBackendParameters'])) {
            throw new \Exception(
                'Secondary backend missing support for getBackendParameters'
            );
        }
        $secondaryParams = $this->secondaryParams->getBackendParameters();
        $params->set(
            'secondary_backend',
            $secondaryParams
        );
        return $params;
    }

    /**
     * Translate a request for the secondary backend
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return \Laminas\Stdlib\Parameters
     */
    protected function translateRequest($request)
    {
        $secondary = $this->blenderConfig['Secondary']['backend'];
        $mappings = $this->mappings['Facets'] ?? [];
        $filters = $request->get('filter');
        if (!empty($filters)) {
            $hierarchicalFacets = [];
            $options = $this->getOptions();
            if (is_callable([$options, 'getHierarchicalFacets'])) {
                $hierarchicalFacets = $options->getHierarchicalFacets();
            }
            $newFilters = [];
            foreach ((array)$filters as $filter) {
                [$field, $value] = $this->parseFilter($filter);
                if ('blender_backend' === $field) {
                    continue;
                }
                $prefix = '';
                if (substr($field, 0, 1) === '~') {
                    $prefix = '~';
                    $field = substr($field, 1);
                }
                $values = [$value];
                if (isset($mappings[$field]['Secondary'])) {
                    // Map facet value
                    $facetType = $mappings[$field]['Type'] ?? '';
                    if ('boolean' === $facetType) {
                        $value = (bool)$value;
                    }
                    $resultValues = [];
                    $valueMappings = $mappings[$field]['Values'] ?? [];
                    if ($valueMappings) {
                        foreach ($valueMappings as $k => $v) {
                            if ('boolean' === $facetType) {
                                $v = (bool)$v;
                            }
                            if ($value === $v) {
                                $resultValues[] = $k;
                            }
                        }
                        // Check also higher levels when converting hierarchical
                        // facets:
                        if (in_array($field, $hierarchicalFacets)) {
                            $levelOffset = -1;
                            do {
                                $levelGood = false;
                                foreach ($valueMappings as $k => $v) {
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
                    }
                    foreach ($mappings[$field]['RegExp'] ?? [] as $regexp) {
                        $search = $regexp['Search'] ?? '';
                        $replace = $regexp['Replace'] ?? '';
                        if ($search) {
                            $resultValues[]
                                = preg_replace("/$search/", $replace, $value);
                        }
                    }
                    if ($resultValues) {
                        $values = $resultValues;
                    }
                    // Map facet type (only after $field is no longer needed)
                    if (isset($mappings[$field]['Secondary'])) {
                        $field = $mappings[$field]['Secondary'];
                    } else {
                        // Facet not supported by secondary
                        continue;
                    }
                }

                // EDS special case:
                if ('EDS' === $secondary) {
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

        $type = $request->get('type');
        if (!empty($type)) {
            foreach ($this->mappings['Search']['Fields'] ?? [] as $field) {
                if ($type === $field['Primary']) {
                    $request->set('type', $field['Secondary']);
                    break;
                }
            }
        }

        $sort = $request->get('sort');
        if (!empty($sort)) {
            foreach ($this->mappings['Sorting']['Fields'] ?? [] as $field) {
                if ($type === $field['Primary']) {
                    $request->set('sort', $field['Secondary']);
                    break;
                }
            }
        }

        return $request;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @param array $allowed List of checkbox filters to return (null for all)
     *
     * @return array
     */
    public function getCheckboxFacets(array $allowed = null)
    {
        $facets = parent::getCheckboxFacets($allowed);

        // Mark other backend filters disabled if one is enabled
        foreach ($facets as $details) {
            [$field] = $this->parseFilter($details['filter']);
            if ('blender_backend' === $field && $details['selected']) {
                foreach ($facets as $key => $current) {
                    [$field] = $this->parseFilter($current['filter']);
                    if ('blender_backend' === $field && !$current['selected']) {
                        $facets[$key]['disabled'] = true;
                    }
                }
                break;
            }
        }

        return $facets;
    }
}
