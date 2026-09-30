<?php

/**
 * API search action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFindApi\Action\SearchApi;

use Exception;
use Laminas\Stdlib\Parameters;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Config\ConfigManager;
use VuFind\Db\Service\OaiResumptionServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\ResumptionToken\ResumptionTokenTrait;
use VuFind\Search\Base\Results;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Solr\Results as SolrResults;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Exception\ApiException;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;

use function count;

/**
 * API search action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class SearchAction extends AbstractApiSearchAndRecordAction
{
    use ResumptionTokenTrait;

    /**
     * Search access permission.
     *
     * @var ?string
     */
    protected ?string $searchAccessPermission = null;

    /**
     * Constructor.
     *
     * @param AuthorizationService          $authorizationService       Authorization service
     * @param DeveloperSettingsService      $developerSettingsService   Developer settings service
     * @param array                         $config                     VuFind configuration
     * @param RecordFormatter               $recordFormatter            Record formatter
     * @param ConfigManager                 $configManager              Configuration manager
     * @param SearchOptionsPluginManager    $searchOptionsPluginManager Search options plugin manager
     * @param FacetFormatter                $facetFormatter             Facet formatter
     * @param ResultsPluginManager          $resultsPluginManager       Search results plugin manager
     * @param SearchRunner                  $searchRunner               Search runner
     * @param OaiResumptionServiceInterface $oaiResumptionService       OAI resumption token
     * @param HierarchicalFacetHelper       $hierarchicalFacetHelper    Hierarchical facet helper
     */
    public function __construct(
        AuthorizationService $authorizationService,
        DeveloperSettingsService $developerSettingsService,
        #[Autowire(config: 'config')]
        array $config,
        RecordFormatter $recordFormatter,
        ConfigManager $configManager,
        SearchOptionsPluginManager $searchOptionsPluginManager,
        protected FacetFormatter $facetFormatter,
        protected ResultsPluginManager $resultsPluginManager,
        protected SearchRunner $searchRunner,
        #[Autowire(container: DbServicePluginManager::class)]
        protected OaiResumptionServiceInterface $oaiResumptionService,
        protected HierarchicalFacetHelper $hierarchicalFacetHelper,
    ) {
        parent::__construct(
            $authorizationService,
            $developerSettingsService,
            $config,
            $recordFormatter,
            $configManager,
            $searchOptionsPluginManager
        );
    }

    /**
     * Perform a search.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->applyApiSettings();

        // Check for access permission from config:
        if (
            $this->searchAccessPermission
            && ($result = $this->getResponseIfAccessDenied($this->searchAccessPermission))
        ) {
            return $result;
        }

        // Check any API key:
        if (!$this->checkRequestForApiKey()) {
            return $this->outputMissingApiKey();
        }

        // Send both GET and POST variables to search class:
        $requestParams = $request->getQueryParams() + $request->getParsedBody();

        // Perform the search:
        $isCursorSearch = (bool)($requestParams['resumptionToken'] ?? false);
        try {
            $result = $isCursorSearch
                ? $this->doCursorSearch($requestParams)
                : $this->doDefaultSearch($requestParams);
        } catch (Exception $e) {
            // Filter output from exceptions and only allow messages from ApiExceptions to be sent to user.
            $isSafeError = $e instanceof ApiException;
            $message = $isSafeError ? $e->getMessage() : 'Error occurred.';
            $errorCode = $isSafeError ? $e->getCode() : 500;
            return $this->output([], self::STATUS_ERROR, $errorCode, $message);
        }
        return $this->output($result, self::STATUS_OK);
    }

    /**
     * Get API specification fragment for services provided by the action.
     *
     * @return array|string An array or a JSON string
     */
    public function getApiSpecFragment(): array|string
    {
        $this->applyApiSettings();

        $results = $this->resultsPluginManager->get($this->getSearchClassId());
        $options = $results->getOptions();
        $params = $results->getParams();

        $templateParams = [
            'config' => $this->config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'searchTypes' => $options->getBasicHandlers(),
            'defaultSearchType' => $options->getDefaultHandler(),
            'recordFields' => $this->recordFormatter->getRecordFieldSpec($this->getRecordFieldConfig()),
            'defaultFields' => $this->getDefaultRecordFieldNames(),
            'optionalFields' => $this->getOptionalRecordFieldNames(),
            'facetConfig' => $params->getFacetConfig(),
            'sortOptions' => $options->getSortOptions(),
            'defaultSort' => $options->getDefaultSortByHandler(),
            'recordRoute' => $this->recordRoute,
            'searchRoute' => $this->searchRoute,
            'searchIndex' => $this->getBackendId(),
            'indexLabel' => $this->indexLabel,
            'modelPrefix' => $this->modelPrefix,
            'maxLimit' => $this->maxLimit,
            'apiKeysEnabled' => $this->developerSettingsService?->apiKeysEnabled() ?? false,
            'apiKeyHeaderField' => $this->apiKeyHeaderField,
            'apiKeyMode' => $this->developerSettingsService?->getApiKeyMode(),
        ];
        return $this->getTemplateRenderer()
            ->renderTemplateAsString(template: 'searchapi/openapi', params: $templateParams);
    }

    /**
     * Apply API settings.
     *
     * @return void
     */
    protected function applyApiSettings(): void
    {
        $options = $this->searchOptionsPluginManager->get($this->getSearchClassId());
        $settings = $options->getAPISettings();
        if (null !== ($maxLimit = $settings['maxLimit'] ?? null)) {
            $this->maxLimit = $maxLimit;
        }
        if (null !== ($cursorLimit = $settings['cursorLimit'] ?? null)) {
            $this->cursorLimit = $cursorLimit;
        }
        // Check for access permission from config:
        if (null !== ($permission = $settings['searchAccessPermission'] ?? null)) {
            $this->searchAccessPermission = $permission;
        }
    }

    /**
     * Perform a search using paging in Solr.
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
        $results = $this->searchRunner->run(
            $request,
            $this->getBackendId(),
            function (
                $runner,
                $params,
                $searchId
            ) use (
                $limit,
                $facets,
                $hierarchicalFacets,
                $recordFields
            ): void {
                foreach ($facets as $facet) {
                    if (!isset($hierarchicalFacets[$facet])) {
                        $params->addFacet($facet);
                    }
                }
                // Set limit to 0 if no record fields were requested to prevent unnecessary loading.
                $params->setLimit($recordFields ? $limit : 0);
            }
        );
        // If we received an EmptySet back, that indicates that the real search failed due to some kind of syntax
		// error, and we should display a warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            throw new ApiException(ApiException::INVALID_SEARCH, 400);
        }
        $response = ['resultCount' => $results->getResultTotal()];

        $records = $this->recordFormatter->format(
            $results->getResults(),
            $recordFields,
            $this->getRecordFieldConfig()
        );
        if ($records) {
            $response['records'] = $records;
        }
        if ($facets) {
            $hierarchicalFacetData = $this->getHierarchicalFacetData(
                $results,
                array_intersect($facets, $hierarchicalFacets)
            );
            if ($facets = $this->facetFormatter->format($request, $results, $hierarchicalFacetData)) {
                $response['facets'] = $facets;
            }
        }
        return $response;
    }

    /**
     * Perform a search using cursor in Solr. Do not send facet information when using cursor.
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
        if ('*' !== $request['resumptionToken']) {
            // Try to load a resumption token for this request
            $resumptionTokenParams = $this->loadResumptionToken($request['resumptionToken']);
            if (null === $resumptionTokenParams) {
                throw new ApiException(ApiException::INVALID_OR_EXPIRED_TOKEN, 400);
            }
            $request = array_merge($request, $resumptionTokenParams);
        }
        $limit = $this->cursorLimit;
        $cursorMark = $request['cursorMark'] ?? '';
        $recordFields = $this->getFieldList($request);
        // Throw an error here, as there is no reason to search for anything, if no record fields were defined
        if (!$recordFields) {
            throw new ApiException(ApiException::INVALID_RECORD_FIELDS, 400);
        }
        $results = $this->searchRunner->run(
            $request,
            $this->getBackendId(),
            function (
                $runner,
                $params,
                $searchId,
                $results
            ) use (
                $cursorMark,
                $limit
            ): void {
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
            $recordFields,
            $this->getRecordFieldConfig()
        );
        if ($records) {
            $response['records'] = $records;
            // Save resumption token if results were found
            $nextCursor = count($records);
            if (!($results instanceof SolrResults)) {
                throw new ApiException(ApiException::INVALID_SEARCH, 400);
            }
            $nextCursorMark = $results->getCursorMark();
            $resumptionToken = $this->createResumptionToken($request, $nextCursor, $nextCursorMark);
            $response['resumptionToken'] = [
                'token' => $resumptionToken->getToken(),
                'expires' => $resumptionToken->getExpiry()->format(VUFIND_DATABASE_DATETIME_FORMAT),
            ];
        }
        return $response;
    }

    /**
     * Get hierarchical facet data for the given facet fields.
     *
     * @param Results $results Results
     * @param array   $facets  Facet fields
     *
     * @return array
     */
    protected function getHierarchicalFacetData(Results $results, array $facets): array
    {
        if (!$facets) {
            return [];
        }
        $params = $results->getParams();
        foreach ($facets as $facet) {
            $params->addFacet($facet, null, false);
        }
        $params->initFromRequest(new Parameters($this->request->getQueryParams()));

        $facetResults = $results->getFullFieldFacets($facets, false, -1, 'count');

        $facetList = [];
        foreach ($facets as $facet) {
            if (empty($facetResults[$facet]['data']['list'])) {
                $facetList[$facet] = [];
                continue;
            }
            $facetList[$facet] = $this->hierarchicalFacetHelper->buildFacetArray(
                $facet,
                $facetResults[$facet]['data']['list'],
                $results->getUrlQuery(),
                false
            );
            $facetList[$facet] = $this->hierarchicalFacetHelper
                ->filterFacets($facet, $facetList[$facet], $results->getOptions());
        }

        return $facetList;
    }
}
