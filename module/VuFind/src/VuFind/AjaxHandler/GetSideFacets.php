<?php
/**
 * "Get Side Facets" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018-2019.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\AjaxHandler;

use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Recommend\SideFacets;
use VuFind\Search\Base\Results;
use VuFind\Search\RecommendListener;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\UrlQueryHelper;
use VuFind\Session\Settings as SessionSettings;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\View\Renderer\RendererInterface;

/**
 * "Get Side Facets" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSideFacets extends \VuFind\AjaxHandler\AbstractBase
    implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Recommend plugin manager
     *
     * @var RecommendPluginManager
     */
    protected $recommendPluginManager;

    /**
     * Search runner
     *
     * @var SearchRunner
     */
    protected $searchRunner;

    /**
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Main facet configuration
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $facetConfig;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings         $ss       Session settings
     * @param RecommendPluginManager  $rpm      Recommend plugin manager
     * @param SearchRunner            $sr       Search runner
     * @param HierarchicalFacetHelper $fh       Facet helper
     * @param \Zend\Config\Config     $fc       Facet config
     * @param RendererInterface       $renderer View renderer
     */
    public function __construct(SessionSettings $ss,
        \VuFind\Recommend\PluginManager $rpm,
        SearchRunner $sr, HierarchicalFacetHelper $fh,
        \Zend\Config\Config $fc, RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->recommendPluginManager = $rpm;
        $this->searchRunner = $sr;
        $this->facetHelper = $fh;
        $this->facetConfig = $fc;
        $this->renderer = $renderer;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        // Allow both GET and POST variables:
        $request = $params->fromQuery() + $params->fromPost();

        $results = $this->getFacetResults($request);
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $this->logError('Faceting request failed');
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        $recommend = $results->getRecommendations($request['location'] ?? 'side');
        $configIndex = $request['configIndex'] ?? 0;
        $recommend = $recommend[(int)$configIndex] ?? [];

        $context = [
            'recommend' => $recommend,
            'params' => $results->getParams(),
            'searchClassId' => DEFAULT_SEARCH_BACKEND
        ];
        if (isset($request['enabledFacets'])) {
            // Render requested facets separately
            $facets = $this->formatFacets(
                $context, $recommend, $request['enabledFacets'], $results
            );
            return $this->formatResponse(compact('facets'));
        }

        // Render full sidefacets
        $html = $this->renderer->render(
            'Recommend/SideFacets.phtml',
            $context
        );
        return $this->formatResponse(compact('html'));
    }

    /**
     * Perform search and return the results
     *
     * @param array $request Request params
     *
     * @return Results
     */
    protected function getFacetResults(array $request)
    {
        $rManager = $this->recommendPluginManager;
        $setupCallback = function ($runner, $params, $searchId) use ($rManager) {
            $listener = new RecommendListener($rManager, $searchId);
            $config = [];
            $rawConfig = $params->getOptions()
                ->getRecommendationSettings($params->getSearchHandler());
            foreach ($rawConfig['side'] as $value) {
                $settings = explode(':', $value);
                if ($settings[0] === 'SideFacetsDeferred') {
                    $settings[0] = 'SideFacets';
                    $config['side'][] = implode(':', $settings);
                }
            }
            $listener->setConfig($config);
            $listener->attach($runner->getEventManager()->getSharedManager());

            $params->setLimit(0);
            if (is_callable([$params, 'setHierarchicalFacetLimit'])) {
                $params->setHierarchicalFacetLimit(-1);
            }
            $options = $params->getOptions();
            $options->disableHighlighting();
            $options->spellcheckEnabled(false);
        };

        $runner = $this->searchRunner;
        return $runner->run(
            $request,
            $request['searchClassId'] ?? DEFAULT_SEARCH_BACKEND,
            $setupCallback
        );
    }

    /**
     * Format facets according to their type
     *
     * @param array      $context   View rendering context
     * @param SideFacets $recommend Recommendation module
     * @param array      $facets    Facets to process
     * @param Results    $results   Search results
     *
     * @return array
     */
    protected function formatFacets($context, SideFacets $recommend, $facets,
        Results $results
    ) {
        $response = [];
        $hierarchicalFacets = [];
        $options = $results->getOptions();
        if (is_callable([$options, 'getHierarchicalFacets'])) {
            $hierarchicalFacets = $options->getHierarchicalFacets();
            $hierarchicalFacetSortOptions
                = $recommend->getHierarchicalFacetSortOptions();
        }
        $facetSet = $recommend->getFacetSet();
        $urlHelper = $results->getUrlQuery();
        foreach ($facets as $facet) {
            if (strpos($facet, ':')) {
                $response[$facet]['checkboxCount']
                    = $this->getCheckboxFacetCount($facet, $results);
            } elseif (in_array($facet, $hierarchicalFacets)) {
                $response[$facet]['list'] = $this->getHierarchicalFacetData(
                    $facet,
                    $hierarchicalFacetSortOptions,
                    $facetSet[$facet]['list'] ?? [],
                    $urlHelper
                );
            } else {
                $context['facet'] = $facet;
                $context['cluster'] = $facetSet[$facet] ?? [];
                $response[$facet]['html'] = $this->renderer->render(
                    'Recommend/SideFacets/facet.phtml',
                    $context
                );
            }
        }
        return $response;
    }

    /**
     * Get the result count for a checkbox facet
     *
     * @param string  $facet   Facet
     * @param Results $results Search results
     *
     * @return int|null
     */
    protected function getCheckboxFacetCount($facet, Results $results)
    {
        // There's currently no good way to return counts for checkbox filters.
        return null;
    }

    /**
     * Get facet data for a hierarchical facet
     *
     * @param string         $facet       Facet
     * @param array          $sortOptions Hierarhical facet sort options
     * @param array          $facetList   Facet list
     * @param UrlQueryHelper $urlHelper   UrlQueryHelper for creating facet URLs
     *
     * @return array
     */
    protected function getHierarchicalFacetData($facet, $sortOptions, $facetList,
        UrlQueryHelper $urlHelper
    ) {
        if (!empty($sortOptions[$facet])) {
            $this->facetHelper->sortFacetList(
                $facetList,
                'top' === $sortOptions[$facet]
            );
        }

        $result = $this->facetHelper->buildFacetArray(
            $facet, $facetList, $urlHelper, false
        );

        if (!empty($this->facetConfig->FacetFilters->$facet)
            || !empty($this->facetConfig->ExcludeFilters->$facet)
        ) {
            $filters = !empty($this->facetConfig->FacetFilters->$facet)
                ? $this->facetConfig->FacetFilters->$facet->toArray() : [];
            $excludeFilters = !empty($this->facetConfig->ExcludeFilters->$facet)
                ? $this->facetConfig->ExcludeFilters->$facet->toArray() : [];

            $result = $this->facetHelper->filterFacets(
                $result,
                $filters,
                $excludeFilters
            );
        }

        return $result;
    }
}
