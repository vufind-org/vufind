<?php

/**
 * "Get Side Facets" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Recommend\SideFacets;
use VuFind\Search\Base\Results;
use VuFind\Search\RecommendListener;
use VuFind\Search\SearchRunner;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

use function in_array;
use function is_callable;

/**
 * "Get Side Facets" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSideFacets extends \VuFind\AjaxHandler\AbstractBase implements \Psr\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param SessionSettings           $ss                     Session settings
     * @param RecommendPluginManager    $recommendPluginManager Recommend plugin manager
     * @param SearchRunner              $searchRunner           Search runner
     * @param TemplateRendererInterface $renderer               Template renderer
     */
    public function __construct(
        SessionSettings $ss,
        protected \VuFind\Recommend\PluginManager $recommendPluginManager,
        protected SearchRunner $searchRunner,
        protected TemplateRendererInterface $renderer
    ) {
        parent::__construct($ss);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $configIndex = $this->getPostOrQueryParam($request, 'configIndex', 0);
        $configLocation = $this->getPostOrQueryParam($request, 'location', 'side');
        $results = $this->getFacetResults($request, $configIndex, $configLocation);
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $this->logError('Faceting request failed');
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        // Set appropriate query suppression / extra field behavior:
        $queryHelper = $results->getUrlQuery();
        $queryHelper->setSuppressQuery((bool)($this->getPostOrQueryParam($request, 'querySuppressed', false)));
        $extraFields = array_filter(explode(',', $this->getPostOrQueryParam($request, 'extraFields', '')));
        foreach ($extraFields as $field) {
            if (null !== ($value = $this->getPostOrQueryParam($request, $field))) {
                $queryHelper->setDefaultParameter($field, $value);
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
            'searchClassId' => $this->getPostOrQueryParam($request, 'searchClassId', DEFAULT_SEARCH_BACKEND),
        ];
        if ($enabledFacets = $this->getPostOrQueryParam($request, 'enabledFacets')) {
            // Render requested facets separately
            $facets = $this->formatFacets(
                $request,
                $context,
                $recommend,
                (array)$enabledFacets,
                $results
            );
            return $this->formatResponse(compact('facets'));
        }

        // Render full sidefacets
        $html = $this->renderer->renderTemplateAsString(
            $request,
            'Recommend/SideFacets.phtml',
            $context
        );
        return $this->formatResponse(compact('html'));
    }

    /**
     * Perform search and return the results.
     *
     * @param ServerRequestInterface $request Request
     * @param string                 $index   Index of SideFacetsDeferred in configuration
     * @param string                 $loc     Location where SideFacetsDeferred is configured
     *
     * @return Results
     */
    protected function getFacetResults(ServerRequestInterface $request, $index, $loc)
    {
        $setupCallback = function ($runner, $params, $searchId) use ($index, $loc): void {
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
            $request->getParsedBody() + $request->getQueryParams(),
            $this->getPostOrQueryParam($request, 'searchClassId', DEFAULT_SEARCH_BACKEND),
            $setupCallback
        );
        // Restore limit overridden by the setup callback above:
        if ($limit = $this->getPostOrQueryParam($request, 'limit')) {
            $results->getParams()->setLimit($limit);
        }
        return $results;
    }

    /**
     * Format facets according to their type.
     *
     * @param ServerRequestInterface $request   Request
     * @param array                  $context   View rendering context
     * @param SideFacets             $recommend Recommendation module
     * @param array                  $facets    Facets to process
     * @param Results                $results   Search results
     *
     * @return array
     */
    protected function formatFacets(
        ServerRequestInterface $request,
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
                $response[$facet]['checkboxCount'] = $recommend->getCheckboxFacetCount($facet);
            } else {
                $context['facet'] = $facet;
                $context['cluster'] = $facetSet[$facet] ?? [
                    'label' => $results->getParams()->getFacetLabel($facet),
                    'list' => [],
                ];
                $context['collapsedFacets'] = [];
                $response[$facet]['html'] = $this->renderer->renderTemplateAsString(
                    $request,
                    'Recommend/SideFacets/facet.phtml',
                    $context
                );
            }
        }
        return $response;
    }
}
