<?php
/**
 * Learning Material Recommendations Module.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace Finna\Recommend;

use Finna\View\Helper\Root\SearchTabs;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Recommend\RecommendInterface;
use VuFind\Search\Base\Params;

/**
 * Learning Material Recommendations Module.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class LearningMaterial implements RecommendInterface
{
    use LoggerAwareTrait;

    /**
     * The first level of a filter field value for learning material.
     *
     * @var string
     */
    const LEARNING_MATERIAL_FILTER_VALUE = 'LearningMaterial';

    /**
     * Array of filter fields checked for the the learning material value.
     *
     * @var array
     */
    const LEARNING_MATERIAL_FILTER_FIELDS = [
        'format',
        'format_ext_str_mv'
    ];

    /**
     * "Search tabs" view helper.
     *
     * @var SearchTabs
     */
    protected $searchTabs;

    /**
     * Url of the learning material search tab.
     *
     * @var string|null
     */
    protected $tabUrl = null;

    /**
     * LearningMaterial constructor.
     *
     * @param SearchTabs $searchTabs "Search tabs" view helper
     */
    public function __construct(SearchTabs $searchTabs)
    {
        $this->searchTabs = $searchTabs;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     *                                            request.
     *
     * @return void
     */
    public function init($params, $request)
    {
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $params = $results->getParams();
        if (!$this->hasLearningMaterialFilter($params)
            || $params->getSearchType() !== 'basic'
        ) {
            return;
        }
        $view = $this->searchTabs->getView();
        $view->results = $results;
        $tabConfig = $this->searchTabs->getTabConfigForParams($params);
        foreach ($tabConfig as $tab) {
            if ('L1' === $tab['id']) {
                $this->tabUrl = $tab['url'] ?? null;
                break;
            }
        }
    }

    /**
     * Does the object contain a Learning Material filter?
     *
     * @param \VuFind\Search\Base\Params $params Search parameters object.
     *
     * @return bool
     */
    protected function hasLearningMaterialFilter(Params $params): bool
    {
        foreach ($params->getFilterList() as $field => $facets) {
            foreach ($facets as $facet) {
                $parts = explode('/', $facet['value']);
                if (null === ($parts[1] ?? null)) {
                    $this->logWarning(
                        'Could not parse facet value "' . $facet['value']
                        . '" for field "' . $field . '"'
                    );
                    continue;
                }
                if (in_array($facet['field'], self::LEARNING_MATERIAL_FILTER_FIELDS)
                    && self::LEARNING_MATERIAL_FILTER_VALUE === $parts[1]
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns the tab url to use in the recommendation, or null if a
     * recommendation should not be shown.
     *
     * @return string|null
     */
    public function getTabUrl(): ?string
    {
        return $this->tabUrl;
    }
}
