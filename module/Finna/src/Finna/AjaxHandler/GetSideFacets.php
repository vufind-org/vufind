<?php
/**
 * "Get Side Facets" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
namespace Finna\AjaxHandler;

use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Search\RecommendListener;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\HierarchicalFacetHelper;
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
        // Send both GET and POST variables to search class:
        $request = $params->fromQuery() + $params->fromPost();

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
            if (is_callable([$params, 'getHierarchicalFacetLimit'])) {
                $params->setHierarchicalFacetLimit(-1);
            }
            $options = $params->getOptions();
            $options->disableHighlighting();
            $options->spellcheckEnabled(false);
        };

        $runner = $this->searchRunner;
        $results = $runner->run($request, DEFAULT_SEARCH_BACKEND, $setupCallback);

        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $this->logError('Solr faceting request failed');
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        $recommend = $results->getRecommendations('side');
        $recommend = reset($recommend);

        $context = [
            'recommend' => $recommend,
            'params' => $results->getParams(),
            'searchClassId' => 'Solr'
        ];
        if (isset($request['enabledFacets'])) {
            // Render requested facets separately
            $response = [];
            $hierarchicalFacets = [];
            $options = $results->getOptions();
            if (is_callable([$options, 'getHierarchicalFacets'])) {
                $hierarchicalFacets = $options->getHierarchicalFacets();
                $hierarchicalFacetSortOptions
                    = $recommend->getHierarchicalFacetSortOptions();
            }
            $checkboxFacets = $results->getParams()->getCheckboxFacets();
            $sideFacetSet = $recommend->getFacetSet();
            $results = $recommend->getResults();
            foreach ($request['enabledFacets'] as $facet) {
                if (strpos($facet, ':')) {
                    foreach ($checkboxFacets as $checkboxFacet) {
                        if ($facet !== $checkboxFacet['filter']) {
                            continue;
                        }
                        list($field, $value) = explode(':', $facet, 2);
                        $checkboxResults = $results->getFacetList(
                            [$field => $value]
                        );
                        if (!isset($checkboxResults[$field]['list'])) {
                            $response[$facet] = null;
                            continue 2;
                        }
                        $count = 0;
                        $truncate = substr($value, -1) === '*';
                        if ($truncate) {
                            $value = substr($value, 0, -1);
                        }
                        foreach ($checkboxResults[$field]['list'] as $item) {
                            if ($item['value'] == $value
                                || ($truncate
                                && preg_match('/^' . $value . '/', $item['value']))
                                || ($item['value'] == 'true' && $value == '1')
                                || ($item['value'] == 'false' && $value == '0')
                            ) {
                                $count += $item['count'];
                            }
                        }
                        $response[$facet]['checkboxCount'] = $count;
                        continue 2;
                    }
                }
                if (in_array($facet, $hierarchicalFacets)) {
                    // Return the facet data for hierarchical facets
                    $facetList = $sideFacetSet[$facet]['list'];

                    if (!empty($hierarchicalFacetSortOptions[$facet])) {
                        $this->facetHelper->sortFacetList(
                            $facetList,
                            'top' === $hierarchicalFacetSortOptions[$facet]
                        );
                    }

                    $facetList = $this->facetHelper->buildFacetArray(
                        $facet, $facetList, $results->getUrlQuery(), false
                    );

                    if (!empty($this->facetConfig->FacetFilters->$facet)
                        || !empty($this->facetConfig->ExcludeFilters->$facet)
                    ) {
                        $filters = !empty($this->facetConfig->FacetFilters->$facet)
                            ? $this->facetConfig->FacetFilters->$facet->toArray()
                            : [];
                        $excludeFilters
                            = !empty($this->facetConfig->ExcludeFilters->$facet)
                            ? $this->facetConfig->ExcludeFilters->$facet->toArray()
                            : [];

                        $facetList = $this->facetHelper->filterFacets(
                            $facetList,
                            $filters,
                            $excludeFilters
                        );
                    }

                    $response[$facet]['list'] = $facetList;
                } else {
                    $context['facet'] = $facet;
                    $context['cluster'] = $sideFacetSet[$facet] ?? [];
                    $response[$facet]['html'] = $this->renderer->render(
                        'Recommend/SideFacets/facet.phtml',
                        $context
                    );
                }
            }
            return $this->formatResponse($response);
        }

        // Render full sidefacets
        $html = $this->renderer->render(
            'Recommend/SideFacets.phtml',
            $context
        );
        return $this->formatResponse(compact('html'));
    }
}
