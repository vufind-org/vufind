<?php
/**
 * Search API Controller
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindApi\Controller;

use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Search API Controller
 *
 * Controls the Search API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class SearchApiController extends \VuFind\Controller\AbstractSearch
    implements ApiInterface
{
    use ApiTrait;

    /**
     * Record formatter
     *
     * @var RecordFormatter
     */
    protected $recordFormatter;

    /**
     * Facet formatter
     *
     * @var FacetFormatter
     */
    protected $facetFormatter;

    /**
     * Default record fields to return if a request does not define the fields
     *
     * @var array
     */
    protected $defaultRecordFields = [];

    /**
     * Permission required for the record endpoint
     *
     * @var string
     */
    protected $recordAccessPermission = 'access.api.Record';

    /**
     * Permission required for the search endpoint
     *
     * @var string
     */
    protected $searchAccessPermission = 'access.api.Search';

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     * @param RecordFormatter         $rf Record formatter
     * @param FacetFormatter          $ff Facet formatter
     */
    public function __construct(ServiceLocatorInterface $sm, RecordFormatter $rf,
        FacetFormatter $ff
    ) {
        parent::__construct($sm);
        $this->recordFormatter = $rf;
        $this->facetFormatter = $ff;
        foreach ($rf->getRecordFields() as $fieldName => $fieldSpec) {
            if (!empty($fieldSpec['vufind.default'])) {
                $this->defaultRecordFields[] = $fieldName;
            }
        }
    }

    /**
     * Get Swagger specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getSwaggerSpecFragment()
    {
        $config = $this->getConfig();
        $results = $this->getResultsManager()->get($this->searchClassId);
        $options = $results->getOptions();
        $params = $results->getParams();
        $params->activateAllFacets();

        $viewParams = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'searchTypes' => $options->getBasicHandlers(),
            'defaultSearchType' => $options->getDefaultHandler(),
            'recordFields' => $this->recordFormatter->getRecordFieldSpec(),
            'defaultFields' => $this->defaultRecordFields,
            'facetConfig' => $params->getFacetConfig(),
            'sortOptions' => $options->getSortOptions(),
            'defaultSort' => $options->getDefaultSortByHandler()
        ];
        $json = $this->getViewRenderer()->render(
            'searchapi/swagger', $viewParams
        );
        return $json;
    }

    /**
     * Execute the request
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic
        // approach since we allow any origin. For more complete CORS handling
        // a module like zfr-cors could be used.
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Access-Control-Allow-Origin: *');
        $request = $this->getRequest();
        if ($request->getMethod() == 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            $headers->addHeaderLine(
                'Access-Control-Allow-Methods', 'GET, POST, OPTIONS'
            );
            $headers->addHeaderLine('Access-Control-Max-Age', '86400');

            return $this->output(null, 204);
        }
        if (null !== $request->getQuery('swagger')) {
            return $this->createSwaggerSpec();
        }
        return parent::onDispatch($e);
    }

    /**
     * Record action
     *
     * @return \Zend\Http\Response
     */
    public function recordAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->recordAccessPermission)) {
            return $result;
        }

        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (!isset($request['id'])) {
            return $this->output([], self::STATUS_ERROR, 400, 'Missing id');
        }

        $loader = $this->serviceLocator->get('VuFind\RecordLoader');
        try {
            if (is_array($request['id'])) {
                $results = $loader->loadBatchForSource($request['id']);
            } else {
                $results[] = $loader->load($request['id']);
            }
        } catch (\Exception $e) {
            return $this->output(
                [], self::STATUS_ERROR, 400,
                'Error loading record'
            );
        }

        $response = [
            'resultCount' => count($results)
        ];
        $requestedFields = $this->getFieldList($request);
        if ($records = $this->recordFormatter->format($results, $requestedFields)) {
            $response['records'] = $records;
        }

        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Search action
     *
     * @return \Zend\Http\Response
     */
    public function searchAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->searchAccessPermission)) {
            return $result;
        }

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (isset($request['limit'])
            && (!ctype_digit($request['limit'])
            || $request['limit'] < 0 || $request['limit'] > 100)
        ) {
            return $this->output([], self::STATUS_ERROR, 400, 'Invalid limit');
        }

        // Sort by relevance by default
        if (!isset($request['sort'])) {
            $request['sort'] = 'relevance';
        }

        $requestedFields = $this->getFieldList($request);

        $facetConfig = $this->getConfig('facets');
        $hierarchicalFacets = isset($facetConfig->SpecialFacets->hierarchical)
            ? $facetConfig->SpecialFacets->hierarchical->toArray()
            : [];

        $runner = $this->serviceLocator->get('VuFind\SearchRunner');
        try {
            $results = $runner->run(
                $request,
                $this->searchClassId,
                function ($runner, $params, $searchId) use (
                    $hierarchicalFacets, $request, $requestedFields
                ) {
                    foreach (isset($request['facet']) ? $request['facet'] : []
                       as $facet
                    ) {
                        if (!isset($hierarchicalFacets[$facet])) {
                            $params->addFacet($facet);
                        }
                    }
                    if ($requestedFields) {
                        $limit = isset($request['limit']) ? $request['limit'] : 20;
                        $params->setLimit($limit);
                    } else {
                        $params->setLimit(0);
                    }
                }
            );
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 400, $e->getMessage());
        }

        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            return $this->output([], self::STATUS_ERROR, 400, 'Invalid search');
        }

        $response = ['resultCount' => $results->getResultTotal()];

        $records = $this->recordFormatter->format(
            $results->getResults(), $requestedFields
        );
        if ($records) {
            $response['records'] = $records;
        }

        $requestedFacets = isset($request['facet']) ? $request['facet'] : [];
        $hierarchicalFacetData = $this->getHierarchicalFacetData(
            array_intersect($requestedFacets, $hierarchicalFacets)
        );
        $facets = $this->facetFormatter->format(
            $request, $results, $hierarchicalFacetData
        );
        if ($facets) {
            $response['facets'] = $facets;
        }

        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Get hierarchical facet data for the given facet fields
     *
     * @param array $facets Facet fields
     *
     * @return array
     */
    protected function getHierarchicalFacetData($facets)
    {
        if (!$facets) {
            return [];
        }
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        foreach ($facets as $facet) {
            $params->addFacet($facet, null, false);
        }
        $params->initFromRequest($this->getRequest()->getQuery());

        $facetResults = $results->getFullFieldFacets($facets, false, -1, 'count');

        $facetHelper = $this->serviceLocator
            ->get('VuFind\HierarchicalFacetHelper');

        $facetList = [];
        foreach ($facets as $facet) {
            if (empty($facetResults[$facet]['data']['list'])) {
                $facetList[$facet] = [];
                continue;
            }
            $facetList[$facet] = $facetHelper->buildFacetArray(
                $facet,
                $facetResults[$facet]['data']['list'],
                $results->getUrlQuery(),
                false
            );
        }

        return $facetList;
    }

    /**
     * Get field list based on the request
     *
     * @param array $request Request params
     *
     * @return array
     */
    protected function getFieldList($request)
    {
        $fieldList = [];
        if (isset($request['field'])) {
            if (!empty($request['field']) && is_array($request['field'])) {
                $fieldList = $request['field'];
            }
        } else {
            $fieldList = $this->defaultRecordFields;
        }
        return $fieldList;
    }
}
