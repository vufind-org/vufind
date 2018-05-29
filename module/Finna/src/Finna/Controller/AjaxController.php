<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Finna\Search\Solr\Params;
use VuFind\RecordDriver\Missing;
use VuFind\Search\RecommendListener;
use VuFindSearch\Query\Query as Query;

/**
 * This controller handles Finna AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{
    use OnlinePaymentControllerTrait,
        SearchControllerTrait,
        CatalogLoginTrait;


    /**
     * Return rendered HTML for record image popup.
     *
     * @return mixed
     */
    public function getImagePopupAjax()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/html');

        $id = $this->params()->fromQuery('id');
        $index = $this->params()->fromQuery('index');
        $publicList = $this->params()->fromQuery('publicList') == '1';
        $listId = $this->params()->fromQuery('listId');

        list($source, $recId) = explode('.', $id, 2);
        if ($source == 'pci') {
            $source = 'Primo';
        } else {
            $source = 'Solr';
        }
        $driver = $this->getRecordLoader()->load($id, $source);

        $context = [
            'driver' => $driver,
            'index' => $index
        ];

        $user = null;
        if ($publicList) {
            // Public list view: fetch list owner
            $listTable = $this->getTable('UserList');
            $list = $listTable->select(['id' => $listId])->current();
            if ($list && $list->isPublic()) {
                $userTable = $this->getTable('User');
                $user = $userTable->getById($list->user_id);
            }
        } else {
            // otherwise, use logged-in user if available
            $user = $this->getUser();
        }

        if ($user && $data = $user->getSavedData($id, $listId)) {
            $notes = [];
            foreach ($data as $list) {
                if (!empty($list->notes)) {
                    $notes[] = $list->notes;
                }
            }
            $context['listNotes'] = $notes;
            if ($publicList) {
                $context['listUser'] = $user;
            }
        }
        $context['enableImagePopupZoom']
            = isset($config->Content->enableImagePopupZoom)
            ? $config->Content->enableImagePopupZoom : false;

        $recordHelper = $this->getViewRenderer()->plugin('record');
        $html = $recordHelper($driver)
            ->renderTemplate('record-image-popup.phtml', $context);
        $response = $this->getResponse();
        $response->setContent($html);
        return $response;
    }

    /**
     * Retrieve recommendations for results in other tabs
     *
     * @return \Zend\Http\Response
     */
    public function getSearchTabsRecommendationsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $config = $this->serviceLocator->get('VuFind\Config')->get('config');
        if (empty($config->SearchTabsRecommendations->recommendations)) {
            return $this->output('', self::STATUS_OK);
        }

        $id = $this->params()->fromPost(
            'searchId', $this->params()->fromQuery('searchId')
        );
        $limit = $this->params()->fromPost(
            'limit', $this->params()->fromQuery('limit', null)
        );

        $table = $this->serviceLocator->get('VuFind\DbTablePluginManager');
        $search = $table->get('Search')->select(['id' => $id])
            ->current();
        if (empty($search)) {
            return $this->output('Search not found', self::STATUS_ERROR, 400);
        }

        $minSO = $search->getSearchObject();
        $results = $this->serviceLocator
            ->get('VuFind\SearchResultsPluginManager');
        $savedSearch = $minSO->deminify($results);
        $params = $savedSearch->getParams();
        $query = $params->getQuery();
        if (!($query instanceof \VuFindSearch\Query\Query)) {
            return $this->output('', self::STATUS_OK);
        }
        $lookfor = $query->getString();
        if (!$lookfor) {
            return $this->output('', self::STATUS_OK);
        }
        $searchClass = $params->getSearchClassId();
        // Don't return recommendations if not configured or for combined view
        // or for search types other than basic search.
        if (empty($config->SearchTabsRecommendations->recommendations[$searchClass])
            || $searchClass == 'Combined' || $params->getSearchType() != 'basic'
        ) {
            return $this->output('', self::STATUS_OK);
        }

        $view = $this->getViewRenderer();
        $view->results = $savedSearch;
        $searchTabsHelper = $this->getViewRenderer()->plugin('searchtabs');
        $searchTabsHelper->setView($view);
        $tabs = $searchTabsHelper->getTabConfig(
            $searchClass,
            $lookfor,
            $params->getQuery()->getHandler()
        );

        $html = '';
        $recommendations = array_map(
            'trim',
            explode(
                ',',
                $config->SearchTabsRecommendations->recommendations[$searchClass]
            )
        );
        foreach ($recommendations as $recommendation) {
            if ($searchClass == $recommendation) {
                // Who would want this?
                continue;
            }
            foreach ($tabs['tabs'] as $tab) {
                if ($tab['id'] == $recommendation) {
                    $uri = new \Zend\Uri\Uri($tab['url']);
                    $runner = $this->serviceLocator->get('VuFind\SearchRunner');
                    $otherResults = $runner->run(
                        $uri->getQueryAsArray(),
                        $tab['class'],
                        function ($runner, $params, $searchId) use ($config) {
                            $params->setLimit(
                                isset(
                                    $config->SearchTabsRecommendations->count
                                ) ? $config->SearchTabsRecommendations->count : 2
                            );
                            $params->setPage(1);
                            $params->resetFacetConfig();
                            $options = $params->getOptions();
                            $options->disableHighlighting();
                        }
                    );
                    if ($otherResults instanceof \VuFind\Search\EmptySet\Results) {
                        continue;
                    }

                    if (null !== $limit) {
                        $tab['url'] .= '&limit=' . urlencode($limit);
                    }
                    $html .= $this->getViewRenderer()->partial(
                        'Recommend/SearchTabs.phtml',
                        [
                            'tab' => $tab,
                            'lookfor' => $lookfor,
                            'handler' => $params->getQuery()->getHandler(),
                            'results' => $otherResults,
                            'params' => $params
                        ]
                    );
                }
            }
        }

        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    public function systemStatusAjax()
    {
        $this->outputMode = 'plaintext';

        // Check system status
        $config = $this->getConfig();
        if (!empty($config->System->healthCheckFile)
            && file_exists($config->System->healthCheckFile)
        ) {
            return $this->output(
                'Health check file exists', self::STATUS_ERROR, 503
            );
        }

        // Test search index
        if ($this->getRequest()->getQuery('index', 1)) {
            try {
                $results = $this->getResultsManager()->get('Solr');
                $params = $results->getParams();
                $params->setQueryIDs(['healthcheck']);
                $results->performAndProcessSearch();
            } catch (\Exception $e) {
                return $this->output(
                    'Search index error: ' . $e->getMessage(),
                    self::STATUS_ERROR,
                    500
                );
            }
        }

        // Test database connection
        try {
            $sessionTable = $this->getTable('Session');
            $sessionTable->getBySessionId('healthcheck', false);
        } catch (\Exception $e) {
            return $this->output(
                'Database error: ' . $e->getMessage(), self::STATUS_ERROR, 500
            );
        }

        // This may be called frequently, don't leave sessions dangling
        $this->serviceLocator->get('VuFind\SessionManager')->destroy();

        return $this->output('', self::STATUS_OK);
    }

    /**
     * Register online paid fines to the ILS.
     *
     * @return \Zend\Http\Response
     */
    public function registerOnlinePaymentAjax()
    {
        $this->outputMode = 'json';
        $res = $this->processPayment($this->getRequest());
        $returnUrl = $this->url()->fromRoute('myresearch-fines');
        return $res['success']
            ? $this->output($returnUrl, self::STATUS_OK)
            : $this->output($returnUrl, self::STATUS_ERROR, 500);
    }

    /**
     * Handle online payment handler notification request.
     *
     * @return void
     */
    public function onlinePaymentNotifyAjax()
    {
        $this->outputMode = 'json';
        $this->processPayment($this->getRequest());
        // This action does not return anything but a HTTP 200 status.
        exit();
    }

    /**
     * Get popular search terms from Piwik
     *
     * @return \Zend\Http\Response
     */
    public function getPiwikPopularSearchesAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
        $config = $this->serviceLocator->get('VuFind\Config')->get('config');

        if (!isset($config->Piwik->url)
            || !isset($config->Piwik->site_id)
            || !isset($config->Piwik->token_auth)
        ) {
            return $this->output('', self::STATUS_ERROR, 400);
        }

        $params = [
            'module'       => 'API',
            'format'       => 'json',
            'method'       => 'Actions.getSiteSearchKeywords',
            'idSite'       => $config->Piwik->site_id,
            'period'       => 'range',
            'date'         => date('Y-m-d', strtotime('-30 days')) . ',' .
                              date('Y-m-d'),
            'token_auth'   => $config->Piwik->token_auth
        ];
        $url = $config->Piwik->url;
        $httpService = $this->serviceLocator->get('VuFind\Http');
        $client = $httpService->createClient($url);
        $client->setParameterGet($params);
        $result = $client->send();
        if (!$result->isSuccess()) {
            $this->logError("Piwik request for popular searches failed, url $url");
            return $this->output('', self::STATUS_ERROR, 500);
        }

        $response = json_decode($result->getBody(), true);
        if (isset($response['result']) && $response['result'] == 'error') {
            $this->logError(
                "Piwik request for popular searches failed, url $url, message: "
                . $response['message']
            );
            return $this->output('', self::STATUS_ERROR, 500);
        }
        $searchPhrases = [];
        foreach ($response as $item) {
            $label = $item['label'];
            // Strip index from the terms
            $pos = strpos($label, '|');
            if ($pos > 0) {
                $label = substr($label, $pos + 1);
            }
            $label = trim($label);
            if (strncmp($label, '(', 1) == 0) {
                // Ignore searches that begin with a parenthesis
                // because they are likely to be advanced searches
                continue;
            } elseif ($label === '-' || $label === '') {
                // Ignore empty searches
                continue;
            }
            $searchPhrases[$label]
                = !isset($item['nb_actions']) || null === $item['nb_actions']
                ? $item['nb_visits']
                : $item['nb_actions'];
        }
        // Order by hits
        arsort($searchPhrases);

        $html = $this->getViewRenderer()->render(
            'ajax/piwik-popular-searches.phtml', ['searches' => $searchPhrases]
        );
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Imports searches and lists from uploaded file as logged in user's favorites.
     *
     * @return mixed
     */
    public function importFavoritesAjax()
    {
        $request = $this->getRequest();
        $user = $this->getUser();

        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $file = $request->getFiles('favorites-file');
        $fileExists = !empty($file['tmp_name']) && file_exists($file['tmp_name']);
        $error = false;

        if ($fileExists) {
            $data = json_decode(file_get_contents($file['tmp_name']), true);
            if ($data) {
                $searches = $this->importSearches($data['searches'], $user->id);
                $lists = $this->importUserLists($data['lists'], $user->id);

                $templateParams = [
                    'searches' => $searches,
                    'lists' => $lists['userLists'],
                    'resources' => $lists['userResources']
                ];
            } else {
                $error = true;
                $templateParams = [
                    'error' => $this->translate(
                        'import_favorites_error_invalid_file'
                    )
                ];
            }
        } else {
            $error = true;
            $templateParams = [
                'error' => $this->translate('import_favorites_error_no_file')
            ];
        }

        $template = $error
            ? 'myresearch/import-error.phtml'
            : 'myresearch/import-success.phtml';
        $html = $this->getViewRenderer()->partial($template, $templateParams);
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Get Autocomplete suggestions.
     *
     * @return \Zend\Http\Response
     */
    protected function getACSuggestionsAjax()
    {
        if ($type = $this->getBrowseAction($this->getRequest())) {
            $query = $this->getRequest()->getQuery();
            $query->set('type', "Browse_$type");
            $query->set('searcher', 'Solr');
        }
        return parent::getACSuggestionsAjax();
    }

    /**
     * Get hierarchical facet data for jsTree
     *
     * Parameters:
     * facetName  The facet to retrieve
     * facetSort  By default all facets are sorted by count. Two values are available
     * for alternative sorting:
     *   top = sort the top level alphabetically, rest by count
     *   all = sort all levels alphabetically
     *
     * @return \Zend\Http\Response
     */
    protected function getFacetDataAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        if ($type = $this->getBrowseAction($this->getRequest())) {
            $config
                = $this->serviceLocator->get('VuFind\Config')->get('browse');

            if (!isset($config[$type])) {
                return $this->output(
                    "Missing configuration for browse action: $type",
                    self::STATUS_ERROR,
                    500
                );
            }

            $config = $config[$type];
            $query = $this->getRequest()->getQuery();
            if (!$query->get('sort')) {
                $query->set('sort', $config['sort'] ?: 'title');
            }
            if (!$query->get('type')) {
                $query->set('type', $config['type'] ?: 'Title');
            }
            $query->set('browseHandler', $query->get('type'));
            $query->set('hiddenFilters', $config['filter']->toArray());
        }

        $result = parent::getFacetDataAjax();

        // Filter facet array. Need to decode the JSON response, which is not quite
        // optimal..
        $resultContent = json_decode($result->getContent(), true);

        $facet = $this->params()->fromQuery('facetName');
        $facetConfig = $this->getConfig('facets');
        if (!empty($facetConfig->FacetFilters->$facet)
            || !empty($facetConfig->ExcludeFilters->$facet)
        ) {
            $facetHelper = $this->serviceLocator
                ->get('VuFind\HierarchicalFacetHelper');
            $filters = !empty($facetConfig->FacetFilters->$facet)
                ? $facetConfig->FacetFilters->$facet->toArray()
                : [];
            $excludeFilters = !empty($facetConfig->ExcludeFilters->$facet)
                ? $facetConfig->ExcludeFilters->$facet->toArray()
                : [];

            $resultContent['data'] = $facetHelper->filterFacets(
                $resultContent['data'],
                $filters,
                $excludeFilters
            );
        }

        $result->setContent(json_encode($resultContent));
        return $result;
    }

    /**
     * Return browse action from the request.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return null|string Browse action or null if request is not a browse action
     */
    protected function getBrowseAction($request)
    {
        $referer = $request->getServer()->get('HTTP_REFERER');
        $match = null;
        $regex = '/^http[s]?:.*\/Browse\/(Database|Journal)[\/.*]?/';
        if (preg_match($regex, $referer, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Return an error response in JSON format and log the error message.
     *
     * @param string $outputMsg  Message to include in the JSON response.
     * @param string $logMsg     Message to output to the error log.
     * @param int    $httpStatus HTTPs status of the JSOn response.
     *
     * @return \Zend\Http\Response
     */
    protected function handleError($outputMsg, $logMsg = '', $httpStatus = 400)
    {
        $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
        $this->logError(
            $outputMsg . ($logMsg ? " ({$logMsg})" : null)
        );

        return $this->output($outputMsg, self::STATUS_ERROR, $httpStatus);
    }

    /**
     * Imports an array of serialized search objects as user's saved searches.
     *
     * @param array $searches Array of search objects
     * @param int   $userId   User id
     *
     * @return int Number of searches saved
     */
    protected function importSearches($searches, $userId)
    {
        $searchTable = $this->getTable('Search');
        $sessId = $this->serviceLocator->get('VuFind\SessionManager')->getId();
        $resultsManager = $this->serviceLocator->get(
            'VuFind\SearchResultsPluginManager'
        );
        $initialSearchCount = count($searchTable->getSavedSearches($userId));

        foreach ($searches as $search) {
            $minifiedSO = unserialize($search);

            if ($minifiedSO) {
                $row = $searchTable->saveSearch(
                    $resultsManager,
                    $minifiedSO->deminify($resultsManager),
                    $sessId,
                    $userId
                );
                $row->user_id = $userId;
                $row->saved = 1;
                $row->save();
            }
        }

        return count($searchTable->getSavedSearches($userId)) - $initialSearchCount;
    }

    /**
     * Imports an array of user lists into database. A single user list is expected
     * to be in following format:
     *
     *   [
     *     title: string
     *     description: string
     *     public: int (0|1)
     *     records: array of [
     *       notes: string
     *       source: string
     *       id: string
     *     ]
     *   ]
     *
     * @param array $lists  User lists
     * @param int   $userId User id
     *
     * @return array [userLists => int, userResources => int], number of new user
     * lists created and number of records to saved into user lists.
     */
    protected function importUserLists($lists, $userId)
    {
        $user = $this->getTable('User')->getById($userId);
        $userListTable = $this->getTable('UserList');
        $userResourceTable = $this->getTable('UserResource');
        $recordLoader = $this->getRecordLoader();
        $favoritesCount = 0;
        $listCount = 0;
        $favorites = $this->serviceLocator
            ->get('VuFind\Favorites\FavoritesService');

        foreach ($lists as $list) {
            $existingList = $userListTable->getByTitle($userId, $list['title']);

            if (!$existingList) {
                $existingList = $userListTable->getNew($user);
                $existingList->title = $list['title'];
                $existingList->description = $list['description'];
                $existingList->public = $list['public'];
                $existingList->save($user);
                $listCount++;
            }

            foreach ($list['records'] as $record) {
                $driver = $recordLoader->load(
                    $record['id'],
                    $record['source'],
                    true
                );

                if ($driver instanceof Missing) {
                    continue;
                }

                $params = [
                    'notes' => $record['notes'],
                    'list' => $existingList->id,
                    'mytags' => $record['tags']
                ];
                $favorites->save($params, $user, $driver);

                if ($record['order'] !== null) {
                    $userResource = $user->getSavedData(
                        $record['id'],
                        $existingList->id,
                        $record['source']
                    )->current();

                    if ($userResource) {
                        $userResourceTable->createOrUpdateLink(
                            $userResource->resource_id,
                            $userId,
                            $existingList->id,
                            $record['notes'],
                            $record['order']
                        );
                    }
                }

                $favoritesCount++;
            }
        }

        return [
            'userLists' => $listCount,
            'userResources' => $favoritesCount
        ];
    }
}
