<?php

/**
 * Search API Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Controller;

use Exception;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Mvc\Exception\DomainException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;

use function count;
use function is_array;

/**
 * Search API Controller
 *
 * Controls the Search API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class SearchApiController extends \VuFind\Controller\AbstractSearch implements ApiInterface
{
    use ApiTrait;
    use \VuFind\ResumptionToken\ResumptionTokenTrait;

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
     * Record route uri
     *
     * @var string
     */
    protected $recordRoute = 'record';

    /**
     * Search route uri
     *
     * @var string
     */
    protected $searchRoute = 'search';

    /**
     * Descriptive label for the index managed by this controller
     *
     * @var string
     */
    protected $indexLabel = 'primary';

    /**
     * Prefix for use in model names used by API
     *
     * @var string
     */
    protected $modelPrefix = '';

    /**
     * Max limit of search results in API response (default 100);
     * Applies to searches not using resumptionToken.
     *
     * @var int
     */
    protected $maxLimit = 100;

    /**
     * Default max limit for cursor based search. Even if cursor search is cheaper in terms of processing in Solr,
     * PHP memory still has limitations so set the default to be a decent amount. (Default 200).
     * Value is adjustable in searches.ini [API] cursorLimit
     *
     * @var int
     */
    protected $cursorLimit = 200;

    /**
     * Facet configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $facetConfig;

    /**
     * Hierarchical facets
     *
     * @var array
     */
    protected $hierarchicalFacets;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm              Service manager
     * @param RecordFormatter         $recordFormatter Record formatter
     * @param FacetFormatter          $facetFormatter  Facet formatter
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected RecordFormatter $recordFormatter,
        protected FacetFormatter $facetFormatter
    ) {
        parent::__construct($sm);
        $this->setResumptionService($this->getDbService(\VuFind\Db\Service\OaiResumptionServiceInterface::class));
        foreach ($recordFormatter->getRecordFields() as $fieldName => $fieldSpec) {
            if (!empty($fieldSpec['vufind.default'])) {
                $this->defaultRecordFields[] = $fieldName;
            }
        }
        // Load configurations from the search options class:
        $options = $sm->get(\VuFind\Search\Options\PluginManager::class)->get($this->searchClassId);
        $settings = $options->getAPISettings();
        $this->facetConfig = $this->getConfig($options->getFacetsIni());
        $this->hierarchicalFacets = $this->facetConfig?->SpecialFacets?->hierarchical?->toArray() ?? [];
        // Apply all supported configurations:
        $configKeys = [
            'recordAccessPermission', 'searchAccessPermission', 'maxLimit', 'cursorLimit',
        ];
        foreach ($configKeys as $key) {
            if (isset($settings[$key])) {
                $this->$key = $settings[$key];
            }
        }
    }

    /**
     * Get API specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getApiSpecFragment()
    {
        $config = $this->getConfig();
        $results = $this->getResultsManager()->get($this->searchClassId);
        $options = $results->getOptions();
        $params = $results->getParams();

        $viewParams = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'searchTypes' => $options->getBasicHandlers(),
            'defaultSearchType' => $options->getDefaultHandler(),
            'recordFields' => $this->recordFormatter->getRecordFieldSpec(),
            'defaultFields' => $this->defaultRecordFields,
            'facetConfig' => $params->getFacetConfig(),
            'sortOptions' => $options->getSortOptions(),
            'defaultSort' => $options->getDefaultSortByHandler(),
            'recordRoute' => $this->recordRoute,
            'searchRoute' => $this->searchRoute,
            'searchIndex' => $this->searchClassId,
            'indexLabel' => $this->indexLabel,
            'modelPrefix' => $this->modelPrefix,
            'maxLimit' => $this->maxLimit,
        ];
        $json = $this->getViewRenderer()->render(
            'searchapi/openapi',
            $viewParams
        );
        return $json;
    }

    /**
     * Execute the request
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws DomainException|InvalidArgumentException|Exception
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
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
                'Access-Control-Allow-Methods',
                'GET, POST, OPTIONS'
            );
            $headers->addHeaderLine('Access-Control-Max-Age', '86400');

            return $this->output(null, 204);
        }
        return parent::onDispatch($e);
    }

    /**
     * Record action
     *
     * @return \Laminas\Http\Response
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

        $loader = $this->getService(\VuFind\Record\Loader::class);
        $results = [];
        try {
            if (is_array($request['id'])) {
                $results = $loader->loadBatchForSource(
                    $request['id'],
                    $this->searchClassId
                );
            } else {
                $results[] = $loader->load($request['id'], $this->searchClassId);
            }
        } catch (Exception $e) {
            return $this->output(
                [],
                self::STATUS_ERROR,
                400,
                'Error loading record'
            );
        }

        $response = [
            'resultCount' => count($results),
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
     * @return \Laminas\Http\Response
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

        $isCursorSearch = ($request['resumptionToken'] ?? false);
        try {
            $response = $isCursorSearch
                ? $this->doCursorSearch($request)
                : $this->doDefaultSearch($request);
        } catch (Exception $e) {
            // Filter output from exceptions and only allow messages from
            // ApiExceptions to be sent to user.
            $isSafeError = $e instanceof ApiException;
            $message = $isSafeError ? $e->getMessage() : 'Error occurred.';
            $errorCode = $isSafeError ? $e->getCode() : 500;
            return $this->output([], self::STATUS_ERROR, $errorCode, $message);
        }
        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Perform a search using page in Solr
     *
     * @param array $request Array containing combination of post and get request params
     *
     * @return array Response to be sent for the user
     *               - records: Records found
     *               - resultCount: Total result count
     *               - facets: array containing facets for the result
     */
    protected function doDefaultSearch(array $request): array
    {
        if (
            isset($request['limit'])
            && (!ctype_digit($request['limit'])
            || $request['limit'] < 0 || $request['limit'] > $this->maxLimit)
        ) {
            throw new ApiException(ApiException::INVALID_LIMIT, 400);
        }
        $limit = $request['limit'] ??= 20;
        $facets = $request['facet'] ??= [];
        $recordFields = $this->getFieldList($request);
        $hierarchicalFacets = $this->hierarchicalFacets;
        $results = $this->getService(\VuFind\Search\SearchRunner::class)->run(
            $request,
            $this->searchClassId,
            function (
                $runner,
                $params,
                $searchId
            ) use (
                $limit,
                $facets,
                $hierarchicalFacets,
                $recordFields
            ) {
                foreach ($facets as $facet) {
                    if (!isset($hierarchicalFacets[$facet])) {
                        $params->addFacet($facet);
                    }
                }
                // Set limit to 0 if no record fields were requested to
                // prevent unnecessary loading.
                $params->setLimit($recordFields ? $limit : 0);
            }
        );
        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            throw new ApiException(ApiException::INVALID_SEARCH, 400);
        }
        $response = ['resultCount' => $results->getResultTotal()];

        $records = $this->recordFormatter->format(
            $results->getResults(),
            $recordFields
        );
        if ($records) {
            $response['records'] = $records;
        }
        if ($facets) {
            $hierarchicalFacetData = $this->getHierarchicalFacetData(
                array_intersect($facets, $hierarchicalFacets)
            );
            if ($facets = $this->facetFormatter->format($request, $results, $hierarchicalFacetData)) {
                $response['facets'] = $facets;
            }
        }
        return $response;
    }

    /**
     * Perform a search using cursor in Solr. Do not send facet information when using cursor
     *
     * @param array $request Array containing combination of post and get request params
     *
     * @return array Response to be sent for the user.
     *               - records: Found records
     *               - resultCount: Total result count
     *               - resumptionToken: Array containing info about resumption token
     *                  - token
     */
    protected function doCursorSearch(array $request): array
    {
        unset($request['page']);
        // Always discard cursors from requests
        $request['cursor'] = 0;
        if ('*' !== $request['resumptionToken']) {
            // Try to load a resumption token for this request
            $resumptionTokenParams = $this->loadResumptionToken($request['resumptionToken']);
            if (null === $resumptionTokenParams) {
                throw new ApiException(ApiException::INVALID_OR_EXPIRED_TOKEN, 400);
            }
            $request = array_merge($request, $resumptionTokenParams);
        }
        $limit = $this->cursorLimit;
        $cursor = $request['cursor'];
        $cursorMark = $request['cursorMark'] ?? '';
        $recordFields = $this->getFieldList($request);
        // Throw an error here, as there is no reason to search for anything, if no record fields were defined
        if (!$recordFields) {
            throw new ApiException(ApiException::INVALID_RECORD_FIELDS, 400);
        }
        $results = $this->getService(\VuFind\Search\SearchRunner::class)->run(
            $request,
            $this->searchClassId,
            function (
                $runner,
                $params,
                $searchId,
                $results
            ) use (
                $cursorMark,
                $limit
            ) {
                $results->overrideStartRecord(1);
                $results->setCursorMark($cursorMark);
                $params->setLimit($limit);
            }
        );
        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            throw new ApiException(ApiException::INVALID_SEARCH, 400);
        }
        $response = ['resultCount' => $results->getResultTotal()];

        $records = $this->recordFormatter->format(
            $results->getResults(),
            $recordFields
        );
        if ($records) {
            $response['records'] = $records;
            // Save resumption token if results were found
            $nextCursor = $cursor += count($records);
            $nextCursorMark = $results->getCursorMark();
            $resumptionToken = $this->createResumptionToken($request, $nextCursor, $nextCursorMark);
            $response['resumptionToken'] = [
                'token' => $resumptionToken->getToken(),
                'expires' => $resumptionToken->getExpiry()->format('Y-m-d H:i:s'),
            ];
        }
        return $response;
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

        $facetHelper = $this->getService(\VuFind\Search\Solr\HierarchicalFacetHelper::class);

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
            $facetList[$facet] = $facetHelper->filterFacets($facet, $facetList[$facet], $results->getOptions());
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
