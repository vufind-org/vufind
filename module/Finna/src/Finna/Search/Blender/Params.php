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
namespace Finna\Search\Blender;

use Finna\Search\Solr\AuthorityHelper;
use Finna\Search\Solr\HierarchicalFacetHelper;

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
class Params extends \Finna\Search\Solr\Params
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
     * @param AuthorityHelper              $authorityHelper Authority helper
     * @param \VuFind\Date\Converter       $dateConverter   Date converter
     * @param \VuFind\Search\Base\Params   $secondaryParams Secondary search params
     * @param \Laminas\Config\Config       $blenderConfig   Blender configuration
     * @param array                        $mappings        Blender mappings
     */
    public function __construct(\VuFind\Search\Base\Options $options,
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper,
        AuthorityHelper $authorityHelper,
        \VuFind\Date\Converter $dateConverter,
        \VuFind\Search\Base\Params $secondaryParams,
        \Laminas\Config\Config $blenderConfig,
        $mappings
    ) {
        parent::__construct(
            $options, $configLoader, $facetHelper, $authorityHelper, $dateConverter
        );

        $this->secondaryParams = $secondaryParams;
        $this->blenderConfig = $blenderConfig;
        $this->mappings = $mappings;
    }

    /**
     * Pull the search parameters
     *
     * @param \Laminas\StdLib\Parameters $request Parameter object representing user
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
     * @param \Laminas\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return \Laminas\StdLib\Parameters
     */
    protected function translateRequest($request)
    {
        $secondary = $this->blenderConfig['Secondary']['backend'];
        $mappings = $this->mappings['Facets'] ?? [];
        $filters = $request->get('filter');
        if (!empty($filters)) {
            $newFilters = [];
            foreach ((array)$filters as $filter) {
                list($field, $value) = $this->parseFilter($filter);
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
                    foreach ($mappings[$field]['Values'] ?? [] as $k => $v) {
                        if ('boolean' === $facetType) {
                            $v = (bool)$v;
                        }
                        if ($value === $v) {
                            $resultValues[] = $k;
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
                if ('EDS' === $secondary) {
                    array_map(
                        [
                            '\VuFindSearch\Backend\EDS\SearchRequestModel',
                            'escapeSpecialCharacters'
                        ],
                        $values
                    );
                }
                if ('Primo' === $secondary) {
                    $prefix = '';
                }
                foreach ($values as $value) {
                    $newFilters[] = $prefix . $field . ':"' . $value . '"';
                }
            }
            $request->set('filter', $newFilters);
        }

        $type = $request->get('type');
        if (!empty($type)) {
            $key = array_search($type, $this->mappings['Search']['Fields'] ?? []);
            if (false !== $key) {
                $request->set('type', $key);
            }
        }

        $sort = $request->get('sort');
        if (!empty($sort)) {
            $key = array_search($sort, $this->mappings['Sorting']['Fields'] ?? []);
            if (false !== $key) {
                $request->set('sort', $key);
            }
        }

        return $request;
    }
}
