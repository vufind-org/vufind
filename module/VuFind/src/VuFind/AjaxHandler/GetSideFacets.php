<?php

/**
 * "Get Side Facets" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2023.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Recommend\SideFacets;
use VuFind\Search\Base\Results;
use VuFind\Search\RecommendListener;
use VuFind\Search\SearchRunner;
use VuFind\Session\Settings as SessionSettings;

use function in_array;
use function is_callable;

/**
 * "Get Side Facets" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSideFacets extends \VuFind\AjaxHandler\AbstractBase implements \Laminas\Log\LoggerAwareInterface
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
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings        $ss       Session settings
     * @param RecommendPluginManager $rpm      Recommend plugin manager
     * @param SearchRunner           $sr       Search runner
     * @param RendererInterface      $renderer View renderer
     */
    public function __construct(
        SessionSettings $ss,
        \VuFind\Recommend\PluginManager $rpm,
        SearchRunner $sr,
        RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->recommendPluginManager = $rpm;
        $this->searchRunner = $sr;
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

        $configIndex = $request['configIndex'] ?? 0;
        $configLocation = $request['location'] ?? 'side';
        $results = $this->getFacetResults($request, $configIndex, $configLocation);
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $this->logError('Faceting request failed');
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        // Set appropriate query suppression / extra field behavior:
        $queryHelper = $results->getUrlQuery();
        $queryHelper->setSuppressQuery((bool)($request['querySuppressed'] ?? false));
        $extraFields = array_filter(explode(',', $request['extraFields'] ?? ''));
        foreach ($extraFields as $field) {
            if (isset($request[$field])) {
                $queryHelper->setDefaultParameter($field, $request[$field]);
            }
        }

        $recommend = $results->getRecommendations($configLocation)[0] ?? null;
        if (null === $recommend) {
            return $this->formatResponse(
                'Invalid config requested',
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        $context = [
            'recommend' => $recommend,
            'params' => $results->getParams(),
            'searchClassId' => $request['searchClassId'] ?? DEFAULT_SEARCH_BACKEND,
        ];
        if (isset($request['enabledFacets'])) {
            // Render requested facets separately
            $facets = $this->formatFacets(
                $context,
                $recommend,
                $request['enabledFacets'],
                $results
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
     * @param array  $request Request params
     * @param string $index   Index of SideFacetsDeferred in configuration
     * @param string $loc     Location where SideFacetsDeferred is configured
     *
     * @return Results
     */
    protected function getFacetResults(array $request, $index, $loc)
    {
        $setupCallback = function ($runner, $params, $searchId) use ($index, $loc) {
            $listener = new RecommendListener(
                $this->recommendPluginManager,
                $searchId
            );
            $config = [];
            $rawConfig = $params->getOptions()
                ->getRecommendationSettings($params->getSearchHandler());
            $settings = explode(':', $rawConfig[$loc][$index] ?? '');
            if ($settings[0] === 'SideFacetsDeferred') {
                $settings[0] = 'SideFacets';
                $config[$loc][] = implode(':', $settings);
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
        $results = $runner->run(
            $request,
            $request['searchClassId'] ?? DEFAULT_SEARCH_BACKEND,
            $setupCallback
        );
        // Restore limit overridden by the setup callback above:
        if ($limit = $request['limit'] ?? null) {
            $results->getParams()->setLimit($limit);
        }
        return $results;
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
    protected function formatFacets(
        $context,
        SideFacets $recommend,
        $facets,
        Results $results
    ) {
        $response = [];
        $facetSet = $recommend->getFacetSet();
        $checkboxFacets = array_column($recommend->getCheckboxFacetSet(), 'filter');
        foreach ($facets as $facet) {
            if (in_array($facet, $checkboxFacets)) {
                $response[$facet]['checkboxCount'] = $this->getCheckboxFacetCount($facet, $results);
            } else {
                $context['facet'] = $facet;
                $context['cluster'] = $facetSet[$facet] ?? [
                    'label' => $results->getParams()->getFacetLabel($facet),
                    'list' => [],
                ];
                $context['collapsedFacets'] = [];
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
}
