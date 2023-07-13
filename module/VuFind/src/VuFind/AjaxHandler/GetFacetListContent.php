<?php

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

class GetFacetListContent extends AbstractBase
{
    protected $searchResultsManager;

    protected $viewRenderer;

    public function __construct(\VuFind\Search\Results\PluginManager $searchResultsManager, $viewRenderer) {
        $this->searchResultsManager = $searchResultsManager;
        $this->viewRenderer = $viewRenderer;

    }

    /**
     * This function is similar to VuFind's SearchController.facetListAction.
     */
    public function handleRequest(Params $queryParams)
    {
        $facet = $queryParams->fromQuery('facet');
        $contains = $queryParams->fromQuery('contains', '');
        $page = $queryParams->fromQuery('page', 1);
        $sort = $queryParams->fromQuery('sort', 'index');
        $limit = $queryParams->fromQuery('limit', 50);
        $operator = $queryParams->fromQuery('operator', 'AND');
        $exclude = intval($queryParams->fromQuery('exclude', 0));
        $searchAction = $queryParams->fromQuery('searchAction', '');
        $urlBase = $queryParams->fromQuery('urlBase', '');

        $results = $this->searchResultsManager->get('Solr');
        $params = $results->getParams();

        $params->initFromRequest($queryParams->getController()->getRequest()->getQuery());
        $params->getOptions()->spellcheckEnabled(false);
        $params->getOptions()->disableHighlighting();
        $params->addFacet($facet);
        if (!empty($contains)) {
            $params->setFacetContains($contains);
        }

        $partialFacets = $results->getPartialFieldFacets(
            [$facet],
            false,
            $limit,
            $sort, // count || index
            $page,
            $operator == 'OR'
        );

        $list = $partialFacets[$facet]['data']['list'] ?? [];

        $html = $this->viewRenderer->render(
            'search/facet-list-content.phtml',
            ['data' => $list,
             'exclude' => $exclude,
             'facet' => $facet,
             'operator' => $operator,
             'page' => $page,
             'results' => $results,
             'anotherPage' => $partialFacets[$facet]['more'] ?? false,

             'searchAction' => $searchAction,
             'urlBase' => $urlBase,
             'active' => $sort,
             'key' => $sort,
            ]
        );

        $response = ['data' => $partialFacets, 'html' => $html];

        return $this->formatResponse($response);
    }
}
