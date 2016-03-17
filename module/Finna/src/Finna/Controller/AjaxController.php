<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;
use VuFindSearch\ParamBag as ParamBag,
    VuFindSearch\Query\Query as Query,
    VuFind\Search\RecommendListener,
    Finna\MetaLib\MetaLibIrdTrait,
    Zend\Cache\StorageFactory,
    Zend\Feed\Reader\Reader,
    Zend\Http\Request as HttpRequest,
    Zend\Session\Container as SessionContainer;
use Finna\Search\Solr\Params;

/**
 * This controller handles Finna AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{
    use MetaLibIrdTrait,
        OnlinePaymentControllerTrait,
        SearchControllerTrait;

    /**
     * Add resources to a list.
     *
     * @return \Zend\Http\Response
     */
    public function addToListAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR, 400);
        }

        // User must be logged in to edit list:
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $params = $this->getRequest()->getPost('params', null);
        $required = ['listId', 'ids'];
        foreach ($required as $param) {
            if (!isset($params[$param])) {
                return $this->output(
                    "Missing parameter '$param'", self::STATUS_ERROR, 400
                );
            }
        }

        $listId = $params['listId'];
        $ids = $params['ids'];

        $table = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get('UserList');
        $list = $table->getExisting($listId);
        if ($list->user_id !== $user->id) {
            return $this->output(
                "Invalid list id", self::STATUS_ERROR, 400
            );
        }

        foreach ($ids as $id) {
            $source = $id[0];
            $recId = $id[1];
            try {
                $driver = $this->getRecordLoader()->load($recId, $source);
                $driver->saveToFavorites(['list' => $listId], $user);
            } catch (\Exception $e) {
                return $this->output(
                    $this->translate('Failed'), self::STATUS_ERROR, 500
                );
            }
        }

        return $this->output('', self::STATUS_OK);
    }

    /**
     * Update or create a list object.
     *
     * @return \Zend\Http\Response
     */
    public function editListAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR, 400);
        }

        // User must be logged in to edit list:
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        $params = $this->getRequest()->getPost('params', null);
        $required = ['id', 'title'];
        foreach ($required as $param) {
            if (!isset($params[$param])) {
                return $this->output(
                    "Missing parameter '$param'", self::STATUS_ERROR, 400
                );
            }
        }
        $id = $params['id'];

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $table = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get('UserList');

        $newList = ($id == 'NEW');
        $list = $newList ? $table->getNew($user) : $table->getExisting($id);

        $finalId = $list->updateFromRequest(
            $user, new \Zend\Stdlib\Parameters($params)
        );

        $params['id'] = $finalId;
        return $this->output($params, self::STATUS_OK);
    }

    /**
     * Update list resource note.
     *
     * @return \Zend\Http\Response
     */
    public function editListResourceAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR, 400);
        }

        // User must be logged in to edit list:
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $params = $this->getRequest()->getPost('params', null);

        $required = ['listId', 'notes'];
        foreach ($required as $param) {
            if (!isset($params[$param])) {
                return $this->output(
                    "Missing parameter '$param'", self::STATUS_ERROR, 400
                );
            }
        }

        list($source, $id) = explode('.', $params['id'], 2);
        $map = ['metalib' => 'MetaLib', 'pci' => 'Primo'];
        $source = isset($map[$source]) ? $map[$source] : DEFAULT_SEARCH_BACKEND;

        $listId = $params['listId'];
        $notes = $params['notes'];

        $resources = $user->getSavedData($params['id'], $listId, $source);
        if (empty($resources)) {
            return $this->output("User resource not found", self::STATUS_ERROR, 400);
        }

        $table = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get('UserResource');

        foreach ($resources as $res) {
            $row = $table->select(['id' => $res->id])->current();
            $row->notes = $notes;
            $row->save();
        }

        return $this->output('', self::STATUS_OK);
    }

    /**
     * Change pickup Locations
     *
     * @return \Zend\Http\Response
     */
    public function changePickUpLocationAjax()
    {
        $requestId = $this->params()->fromQuery('requestId');
        $pickupLocationId = $this->params()->fromQuery('pickupLocationId');
        if (empty($requestId)) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }

        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                [
                    'status' => false,
                    'msg' => $this->translate('You must be logged in first')
                ],
                self::STATUS_NEED_AUTH
            );
        }

        try {
            $catalog = $this->getILS();
            $patron = $this->getILSAuthenticator()->storedCatalogLogin();

            if ($patron) {
                $details = [
                    'requestId'    => $requestId,
                    'pickupLocationId' => $pickupLocationId
                ];
                $results = [];

                $results = $catalog->changePickupLocation($patron, $details);

                return $this->output($results, self::STATUS_OK);
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Check Requests are Valid
     *
     * @return \Zend\Http\Response
     */
    public function checkRequestsAreValidAjax()
    {
        $this->disableSessionWrites(); // avoid session write timing bug
        $id = $this->params()->fromPost('id', $this->params()->fromQuery('id'));
        $data = $this->params()->fromPost(
            'data', $this->params()->fromQuery('data')
        );
        $requestType = $this->params()->fromPost(
            'requestType', $this->params()->fromQuery('requestType')
        );
        if (!empty($id) && !empty($data)) {
            // check if user is logged in
            $user = $this->getUser();
            if (!$user) {
                return $this->output(
                    [
                        'status' => false,
                        'msg' => $this->translate('You must be logged in first')
                    ],
                    self::STATUS_NEED_AUTH
                );
            }

            try {
                $catalog = $this->getILS();
                $patron = $this->getILSAuthenticator()->storedCatalogLogin();
                if ($patron) {
                    $results = [];
                    foreach ($data as $item) {
                        switch ($requestType) {
                        case 'ILLRequest':
                            $result = $catalog->checkILLRequestIsValid(
                                $id, $item, $patron
                            );

                            $msg = $result
                                ? $this->translate('ill_request_place_text')
                                : $this->translate('ill_request_error_blocked');
                            break;
                        case 'StorageRetrievalRequest':
                            $result = $catalog->checkStorageRetrievalRequestIsValid(
                                $id, $item, $patron
                            );

                            $msg = $result
                                ? $this->translate(
                                    'storage_retrieval_request_place_text'
                                )
                                : $this->translate(
                                    'storage_retrieval_request_error_blocked'
                                );
                            break;
                        default:
                            $result = $catalog->checkRequestIsValid(
                                $id, $item, $patron
                            );

                            $msg = $result
                                ? $this->translate('request_place_text')
                                : $this->translate('hold_error_blocked');
                            break;
                        }
                        $results[] = [
                            'status' => $result,
                            'msg' => $msg
                        ];
                    }
                    return $this->output($results, self::STATUS_OK);
                }
            } catch (\Exception $e) {
                // Do nothing -- just fail through to the error message below.
            }
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function commentRecordAjax()
    {
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $type = $this->params()->fromPost('type');
        $id = $this->params()->fromPost('id');
        $table = $this->getTable('Comments');
        if ($commentId = $this->params()->fromPost('commentId')) {
            // Edit existing comment
            $comment = $this->params()->fromPost('comment');
            if (empty($commentId) || empty($comment)) {
                return $this->output(
                    $this->translate('An error has occurred'),
                    self::STATUS_ERROR,
                    500
                );
            }
            $rating = $this->params()->fromPost('rating');
            $this->getTable('Comments')
                ->edit($user->id, $commentId, $comment, $rating);

            $output = ['id' => $commentId];
            if ($rating) {
                $average = $table->getAverageRatingForResource($id);
                $output['rating'] = $average;
            }
            return $this->output($output, self::STATUS_OK);
        }

        if ($type === '1') {
            // Allow only 1 rating/record for each user
            $comments = $table->getForResourceByUser($id, $user->id);
            if (count($comments)) {
                return $this->output(
                    $this->translate('An error has occurred'),
                    self::STATUS_ERROR,
                    500
                );
            }
        }

        $output = parent::commentRecordAjax();
        $data = json_decode($output->getContent(), true);

        if ($data['status'] != 'OK' || !isset($data['data'])) {
            return $output;
        }

        $commentId = $data['data'];
        $output = ['id' => $commentId];

        // Update type
        $table->setType($user->id, $commentId, $type);

        // Update rating
        $rating = $this->getRequest()->getPost()->get('rating');
        $updateRating = $rating !== null && $rating > 0 && $rating <= 5;
        if ($updateRating) {
            $table = $this->getTable('Comments');
            $table->setRating($user->id, $commentId, $rating);
        }

        // Add comment to deduplicated records
        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');
        $results = $runner->run(
            ['lookfor' => 'local_ids_str_mv:"' . addcslashes($id, '"') . '"'],
            'Solr',
            function ($runner, $params, $searchId) {
                $params->setLimit(100);
                $params->setPage(1);
                $params->resetFacetConfig();
                $options = $params->getOptions();
                $options->disableHighlighting();
            }
        );
        $ids = [$id];

        if (!$results instanceof \VuFind\Search\EmptySet\Results
            && count($results->getResults())
        ) {
            $results = $results->getResults();
            $ids = reset($results)->getLocalIds();
        }

        $commentsRecord = $this->getTable('CommentsRecord');
        $commentsRecord->addLinks($commentId, $ids);

        if ($updateRating) {
            $average = $table->getAverageRatingForResource($id);
            $output['rating'] = $average;
        }

        return $this->output($output, self::STATUS_OK);
    }

    /**
     * Delete a comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function deleteRecordCommentAjax()
    {
        $output = parent::deleteRecordCommentAjax();
        $data = json_decode($output->getContent(), true);

        if ($data['status'] != 'OK') {
            return $output;
        }

        $recordId = $this->params()->fromQuery('recordId');
        if ($recordId !== null) {
            $table = $this->getTable('Comments');
            $average = $table->getAverageRatingForResource($recordId);
            $this->output(['rating' => $average], self::STATUS_OK);
        }

        return $output;
    }

    /**
     * Return data for date range visualization module in JSON format.
     *
     * @return mixed
     */
    public function dateRangeVisualAjax()
    {
        $this->disableSessionWrites(); // avoid session write timing bug
        $backend = $this->params()->fromQuery('backend');
        if (!$backend) {
            $backend = 'solr';
        }
        $isSolr = $backend == 'solr';

        $configFile = $isSolr ? 'facets' : 'Primo';
        $config
            = $this->getServiceLocator()->get('VuFind\Config')->get($configFile);
        if (!isset($config->SpecialFacets->dateRangeVis)) {
            return $this->output([], self::STATUS_ERROR, 400);
        }

        list($filterField, $facet)
            = explode(':', $config->SpecialFacets->dateRangeVis);

        $facetList = $this->getFacetList($isSolr, $filterField, $facet);

        if (empty($facetList)) {
            return $this->output([], self::STATUS_OK);
        }

        $res = [];
        $min = PHP_INT_MAX;
        $max = -$min;

        foreach ($facetList as $f) {
            $count = $f['count'];
            $val = $f['displayText'];
            // Only retain numeric values
            if (!preg_match("/^-?[0-9]+$/", $val)) {
                continue;
            }
            $min = min($min, (int)$val);
            $max = max($max, (int)$val);
            $res[] = [$val, $count];
        }
        $res = [$facet => ['data' => $res, 'min' => $min, 'max' => $max]];
        return $this->output($res, self::STATUS_OK);
    }

    /**
     * Retrieve bX recommendations
     *
     * @return \Zend\Http\Response
     */
    public function getBxRecommendationsAjax()
    {
        $this->disableSessionWrites(); // avoid session write timing bug
        // avoid session write timing bug
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        if (!isset($config->bX['token'])) {
            return $this->output('bX support not enabled', self::STATUS_ERROR, 400);
        }

        $id = $this->params()->fromPost('id', $this->params()->fromQuery('id'));
        $parts = explode('|', $id, 2);
        if (count($parts) < 2) {
            $source = DEFAULT_SEARCH_BACKEND;
            $id = $parts[0];
        } else {
            $source = $parts[0];
            $id = $parts[1];
        }

        $driver = $this->getServiceLocator()->get('VuFind\RecordLoader')
            ->load($id, $source);
        $openUrl = $driver->tryMethod('getOpenUrl', [true]);
        if (empty($openUrl)) {
            return $this->output([], self::STATUS_OK);
        }

        $params = http_build_query(
            [
                'token' => $config->bX['token'],
                'format' => 'xml',
                'source' => isset($config->bX['source']) ? $config->bX['source']
                    : 'global',
                'maxRecords' => isset($config->bX['maxRecords'])
                    ? $config->bX['maxRecords'] : '5',
                'threshold' => isset($config->bX['threshold'])
                    ? $config->bX['threshold'] : '50'
            ]
        );
        $openUrl .= '&res_dat=' . urlencode($params);

        $baseUrl = isset($config->bX['baseUrl'])
            ? $config->bX['baseUrl']
            : 'http://recommender.service.exlibrisgroup.com/service/recommender/'
            . 'openurl';

        // Create Proxy Request
        $httpService = $this->getServiceLocator()->get('VuFind\Http');
        $client = $httpService->createClient("$baseUrl?$openUrl");
        $result = $client->setMethod('GET')->send();

        if ($result->isSuccess()) {
            // Even if we get a response, make sure it's a 'good' one.
            if ($result->getStatusCode() != 200) {
                return $this->output(
                    'bX request failed, response code ' . $result->getStatusCode(),
                    self::STATUS_ERROR,
                    500
                );
            }
        } else {
            return $this->output(
                'bX request failed: ' . $result->getStatusCode()
                . ': ' . $result->getReasonPhrase(),
                self::STATUS_ERROR,
                500
            );
        }
        $xml = simplexml_load_string($result->getBody());
        $recommendations = [];
        $jnl = 'info:ofi/fmt:xml:xsd:journal';
        $xml->registerXPathNamespace('jnl', $jnl);
        foreach ($xml->xpath('//jnl:journal') as $journal) {
            $item = $this->convertToArray($journal, $jnl);
            unset($item['authors']['author']);
            $item['openurl'] = $this->createBxOpenUrl($item);
            $recommendations[] = $item;
        }
        $html = $this->getViewRenderer()->partial(
            'Recommend/bx.phtml',
            ['recommendations' => $recommendations]
        );
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Return record description in JSON format.
     *
     * @return \Zend\Http\Response
     */
    public function getDescriptionAjax()
    {
        if (!$id = $this->params()->fromQuery('id')) {
            return $this->output('', self::STATUS_ERROR, 400);
        }

        $cacheDir = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('description')->getOptions()->getCacheDir();

        $localFile = "$cacheDir/" . urlencode($id) . '.txt';

        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $maxAge = isset($config->Content->summarycachetime)
            ? $config->Content->summarycachetime : 1440;

        if (is_readable($localFile)
            && time() - filemtime($localFile) < $maxAge * 60
        ) {
            // Load local cache if available
            if (($content = file_get_contents($localFile)) !== false) {
                return $this->output($content, self::STATUS_OK);
            } else {
                return $this->output('', self::STATUS_ERROR, 500);
            }
        } else {
            // Get URL
            $driver = $this->getRecordLoader()->load($id, 'Solr');
            $url = $driver->getDescriptionURL();
            // Get, manipulate, save and display content if available
            if ($url) {
                if ($content = @file_get_contents($url)) {
                    $content = preg_replace('/.*<.B>(.*)/', '\1', $content);

                    $content = strip_tags($content);

                    // Replace line breaks with <br>
                    $content = preg_replace(
                        '/(\r\n|\n|\r){3,}/', '<br><br>', $content
                    );

                    $content = utf8_encode($content);
                    file_put_contents($localFile, $content);

                    return $this->output($content, self::STATUS_OK);
                }
            }
            if ($summary = $driver->getSummary()) {
                return $this->output(
                    implode('<br><br>', $summary), self::STATUS_OK
                );
            }
        }
        return $this->output('', self::STATUS_OK);
    }

    /**
     * Return feed content and settings in JSON format.
     *
     * @return mixed
     */
    public function getFeedAjax()
    {
        if (!$id = $this->params()->fromQuery('id')) {
            return $this->output('Missing feed id', self::STATUS_ERROR, 400);
        }

        $touchDevice = $this->params()->fromQuery('touch-device') !== null
            ? $this->params()->fromQuery('touch-device') === '1'
            : false
        ;

        $config = $this->getServiceLocator()->get('VuFind\Config')->get('rss');
        if (!isset($config[$id])) {
            return $this->output(
                'Missing feed configuration', self::STATUS_ERROR, 400
            );
        }

        $config = $config[$id];
        if (!$config->active) {
            return $this->output('Feed inactive', self::STATUS_ERROR, 400);
        }

        if (!$url = $config->url) {
            return $this->output('Missing feed URL', self::STATUS_ERROR, 500);
        }

        $translator = $this->getServiceLocator()->get('VuFind\Translator');
        $language   = $translator->getLocale();
        if (isset($url[$language])) {
            $url = trim($url[$language]);
        } else if (isset($url['*'])) {
            $url = trim($url['*']);
        } else {
            return $this->output('Missing feed URL', self::STATUS_ERROR, 500);
        }

        $type = $config->type;
        $channel = null;

        // Check for cached version
        $cacheEnabled = false;
        $cacheDir = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('feed')->getOptions()->getCacheDir();

        $cacheKey = $config->toArray();
        $cacheKey['language'] = $language;

        $localFile = "$cacheDir/" . md5(var_export($cacheKey, true)) . '.xml';
        $cacheConfig = $this->getServiceLocator()
            ->get('VuFind\Config')->get('config');
        $maxAge = isset($cacheConfig->Content->feedcachetime)
            ? $cacheConfig->Content->feedcachetime : false;

        $httpService = $this->getServiceLocator()->get('VuFind\Http');
        Reader::setHttpClient($httpService->createClient());

        if ($maxAge) {
            $cacheEnabled = true;
            if (is_readable($localFile)
                && time() - filemtime($localFile) < $maxAge * 60
            ) {
                $channel = Reader::importFile($localFile);
            }
        }

        if (!$channel) {
            // No cache available, read from source.
            if (preg_match('/^http(s)?:\/\//', $url)) {
                // Absolute URL
                $channel = Reader::import($url);
            } else if (substr($url, 0, 1) === '/') {
                // Relative URL
                $url = substr($this->getServerUrl('home'), 0, -1) . $url;
                $channel = Reader::import($url);
            } else {
                // Local file
                if (!is_file($url)) {
                    return $this->output(
                        "File $url could not be found", self::STATUS_ERROR, 500
                    );
                }
                $channel = Reader::importFile($url);
            }
        }

        if (!$channel) {
            return $this->output('Parsing failed', self::STATUS_ERROR, 500);
        }

        if ($cacheEnabled) {
            file_put_contents($localFile, $channel->saveXml());
        }

        $content = [
            'title' => 'getTitle',
            'text' => 'getContent',
            'image' => 'getEnclosure',
            'link' => 'getLink',
            'date' => 'getDateCreated'
        ];

        $dateFormat = isset($config->dateFormat) ? $config->dateFormat : 'j.n.';
        $itemsCnt = isset($config->items) ? $config->items : null;

        $items = [];
        foreach ($channel as $item) {
            $data = [];
            foreach ($content as $setting => $method) {
                if (!isset($config->content[$setting])
                    || $config->content[$setting] != 0
                ) {
                    $tmp = $item->{$method}();
                    if (is_object($tmp)) {
                        $tmp = get_object_vars($tmp);
                    }

                    if ($setting == 'image') {
                        if (!$tmp
                            || stripos($tmp['type'], 'image') === false
                        ) {
                            // Attempt to parse image URL from content
                            if ($tmp = $this->extractImage($item->getContent())) {
                                $tmp = ['url' => $tmp];
                            }
                        }
                    } else if ($setting == 'date') {
                        if (isset($tmp['date'])) {
                            $tmp = new \DateTime(($tmp['date']));
                            $tmp = $tmp->format($dateFormat);
                        }
                    } else {
                        if (is_string($tmp)) {
                            $tmp = strip_tags($tmp);
                        }
                    }
                    if ($tmp) {
                        $data[$setting] = $tmp;
                    }
                }
            }

            // Make sure that we have something to display
            $accept = $data['title'] && trim($data['title']) != ''
                || $data['text'] && trim($data['text']) != ''
                || $data['image']
            ;
            if (!$accept) {
                continue;
            }

            $items[] = $data;
            if ($itemsCnt !== null) {
                if (--$itemsCnt === 0) {
                    break;
                }
            }
        }

        $images
            = isset($config->content['image'])
            ? $config->content['image'] : true;

        $moreLink = !isset($config->moreLink) || $config->moreLink
            ? $channel->getLink() : null;

        $key = $touchDevice ? 'touch' : 'desktop';
        $linkText = null;
        if (isset($config->linkText[$key])) {
            $linkText = $config->linkText[$key];
        } else if (isset($config->linkText) && is_string($config->linkText)) {
            $linkText = $config->linkText;
        }

        $feed = [
            'linkText' => $linkText,
            'moreLink' => $moreLink,
            'type' => $type,
            'items' => $items,
            'touchDevice' => $touchDevice,
            'images' => $images
        ];

        if (isset($config->title)) {
            if ($config->title == 'rss') {
                $feed['title'] = $channel->getTitle();
            } else {
                $feed['translateTitle'] = $config->title;
            }
        }

        if (isset($config->linkTarget)) {
            $feed['linkTarget'] = $config->linkTarget;
        }

        $template = $type == 'list' ? 'list' : 'carousel';
        $html = $this->getViewRenderer()->partial(
            "ajax/feed-$template.phtml", $feed
        );

        $settings = [];
        $settings['type'] = $type;
        if (isset($config->height)) {
            $settings['height'] = $config->height;
        }

        if ($type == 'carousel' || $type == 'carousel-vertical') {
            $settings['images'] = $images;
            $settings['autoplay']
                = isset($config->autoplay) ? $config->autoplay : false;
            $settings['dots']
                = isset($config->dots) ? $config->dots == true : true;
            $breakPoints
                = ['desktop' => 4, 'desktop-small' => 3,
                   'tablet' => 2, 'mobile' => 1];

            foreach ($breakPoints as $breakPoint => $default) {
                $settings['slidesToShow'][$breakPoint]
                    = isset($config->itemsPerPage[$breakPoint])
                    ? (int)$config->itemsPerPage[$breakPoint] : $default;

                $settings['scrolledItems'][$breakPoint]
                    = isset($config->scrolledItems[$breakPoint])
                    ? (int)$config->scrolledItems[$breakPoint]
                    : $settings['slidesToShow'][$breakPoint];
            }

            if ($type == 'carousel') {
                $settings['titlePosition']
                    = isset($config->titlePosition) ? $config->titlePosition : null;
            }
        }

        $res = ['html' => $html, 'settings' => $settings];
        return $this->output($res, self::STATUS_OK);
    }

    /**
     * Return rendered HTML for record image popup.
     *
     * @return mixed
     */
    public function getImagePopupAjax()
    {
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

        $view = $this->createViewModel();
        $view->setTemplate('RecordDriver/SolrDefault/record-image-popup.phtml');
        $view->setTerminal(true);
        $view->driver = $driver;
        $view->index = $index;

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
            $view->listNotes = $notes;
            if ($publicList) {
                $view->listUser = $user;
            }
        }

        return $view;
    }

    /**
     * Return rendered HTML for my lists navigation.
     *
     * @return \Zend\Http\Response
     */
    public function getMyListsAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR, 400);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        $activeId = (int)$this->getRequest()->getPost('active', null);
        $lists = $user->getLists();
        $html = $this->getViewRenderer()->partial(
            'myresearch/mylist-navi.phtml',
            ['user' => $user, 'activeId' => $activeId, 'lists' => $lists]
        );
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Retrieve recommendations for results in other tabs
     *
     * @return \Zend\Http\Response
     */
    public function getSearchTabsRecommendationsAjax()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        if (empty($config->SearchTabsRecommendations->recommendations)) {
            return $this->output('', self::STATUS_OK);
        }

        $id = $this->params()->fromPost(
            'searchId', $this->params()->fromQuery('searchId')
        );
        $limit = $this->params()->fromPost(
            'limit', $this->params()->fromQuery('limit', null)
        );

        $table = $this->getServiceLocator()->get('VuFind\DbTablePluginManager');
        $search = $table->get('Search')->select(['id' => $id])
            ->current();
        if (empty($search)) {
            return $this->output('Search not found', self::STATUS_ERROR, 400);
        }

        $minSO = $search->getSearchObject();
        $results = $this->getServiceLocator()
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
            foreach ($tabs as $tab) {
                if ($tab['id'] == $recommendation) {
                    $uri = new \Zend\Uri\Uri($tab['url']);
                    $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');
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
                            'results' => $otherResults
                        ]
                    );
                }
            }
        }

         return $this->output($html, self::STATUS_OK);
    }

    /**
     * Retrieve side facets
     *
     * @return \Zend\Http\Response
     */
    public function getSideFacetsAjax()
    {
        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        $rManager = $this->getServiceLocator()->get('VuFind\RecommendPluginManager');
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
        };

        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');
        $results = $runner->run($request, 'Solr', $setupCallback);

        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            return $this->output('', self::STATUS_ERROR, 500);
        }

        $recommend = $results->getRecommendations('side');
        $recommend = reset($recommend);

        $view = $this->getViewRenderer();
        $view->recommend = $recommend;
        $view->params = $results->getParams();
        $html = $view->partial('Recommend/SideFacets.phtml');

        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Report comment inappropriate.
     *
     * @return void
     */
    public function inappropriateCommentAjax()
    {
        $user = $this->getUser();

        $query = $this->getRequest()->getPost();
        if (!$comment = $query->get('comment')) {
            return $this->output(
                $this->translate('Missing comment id'),
                self::STATUS_ERROR,
                400
            );
        }
        if (!$reason = $query->get('reason')) {
            return $this->output(
                $this->translate('Missing reason'),
                self::STATUS_ERROR,
                400
            );
        }
        $table = $this->getTable('Comments');
        $table->markInappropriate($user ? $user->id : null, $comment, $reason);

        if (!$user) {
            $session = new SessionContainer('inappropriateComments');
            if (!isset($session->comments)) {
                $session->comments = [];
            }
            $session->comments[] = $comment;
        }
        return $this->output('', self::STATUS_OK);
    }

    /**
     * Mozilla Persona login
     *
     * @return mixed
     */
    public function personaLoginAjax()
    {
        try {
            $request = $this->getRequest();
            $auth = $this->getServiceLocator()->get('VuFind\AuthManager');
            // Add auth method to POST
            $request->getPost()->set('auth_method', 'MozillaPersona');
            $user = $auth->login($request);
        } catch (Exception $e) {
            return $this->output(false, self::STATUS_ERROR, 500);
        }

        return $this->output(true, self::STATUS_OK);
    }

    /**
     * Mozilla Persona logout
     *
     * @return mixed
     */
    public function personaLogoutAjax()
    {
        $auth = $this->getServiceLocator()->get('VuFind\AuthManager');
        // Logout routing is done in finna-persona.js file.
        $auth->logout($this->getServerUrl('home'));
        return $this->output(true, self::STATUS_OK);
    }

    /**
     * Retrieve similar records
     *
     * @return \Zend\Http\Response
     */
    public function similarRecordsAction()
    {
        $this->disableSessionWrites(); // avoid session write timing bug

        $id = $this->params()->fromPost('id', $this->params()->fromQuery('id'));

        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $similar = $this->getServiceLocator()->get('VuFind\RelatedPluginManager')
            ->get('Similar');

        $driver = $recordLoader->load($id);

        $similar->init('', $driver);

        $html = $this->getViewRenderer()->partial(
            'Related/Similar.phtml',
            ['related' => $similar]
        );

        // Set headers:
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/html');

        // Render results:
        $response->setContent($html);

        return $response;
    }

    /**
     * Perform a MetaLib search.
     *
     * @return \Zend\Http\Response
     */
    public function metaLibAjax()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('MetaLib');
        if (!isset($config->General->enabled) || !$config->General->enabled) {
            throw new \Exception('MetaLib is not enabled');
        }

        $this->getRequest()->getQuery()->set('ajax', 1);

        $metalib = $this->getResultsManager()->get('MetaLib');
        $params = $metalib->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());

        $result = [];
        list($isIRD, $set)
            = $this->getMetaLibSet($params->getMetaLibSearchSet());
        if ($irds = $this->getMetaLibIrds($set)) {
            $params->setIrds($irds);
            $view = $this->forwardTo('MetaLib', 'Search');
            $recordsFound = $view->results->getResultTotal() > 0;
            $lookfor
                = $view->results->getUrlQuery()->isQuerySuppressed()
                ? '' : $view->params->getDisplayQuery();
            $viewParams = [
                'results' => $view->results,
                'metalib' => true,
                'params' => $params,
                'lookfor' => $lookfor
            ];
            $result['searchId'] = $view->results->getSearchId();
            $result['content'] = $this->getViewRenderer()->render(
                $recordsFound ? 'search/list-list.phtml' : 'metalib/nohits.phtml',
                $viewParams
            );
            $result['paginationBottom'] = $this->getViewRenderer()->render(
                'metalib/pagination-bottom.phtml', $viewParams
            );
            $result['paginationTop'] = $this->getViewRenderer()->render(
                'metalib/pagination-top.phtml', $viewParams
            );
            $result['searchTools'] = $this->getViewRenderer()->render(
                'metalib/search-tools.phtml', $viewParams
            );

            $successful = $view->results->getSuccessfulDatabases();
            $errors = $view->results->getFailedDatabases();
            $failed = isset($errors['failed']) ? $errors['failed'] : [];
            $disallowed = isset($errors['disallowed']) ? $errors['disallowed'] : [];

            if ($successful) {
                $result['successful'] = $this->getViewRenderer()->render(
                    'metalib/status-successful.phtml',
                    [
                        'successful' => $successful,
                    ]
                );
            }
            if ($failed || $disallowed) {
                $result['failed'] = $this->getViewRenderer()->render(
                    'metalib/status-failed.phtml',
                    [
                        'failed' => $failed,
                        'disallowed' => $disallowed
                    ]
                );
            }

            $viewParams
                = array_merge(
                    $viewParams,
                    [
                        'lookfor' => $lookfor,
                        'overrideSearchHeading' => null,
                        'startRecord' => $view->results->getStartRecord(),
                        'endRecord' => $view->results->getEndRecord(),
                        'recordsFound' => $recordsFound,
                        'searchType' => $view->params->getsearchType(),
                        'searchClassId' => 'MetaLib'
                    ]
                );
            $result['header'] = $this->getViewRenderer()->render(
                'search/header.phtml', $viewParams
            );
        } else {
            $result['content'] = $result['paginationBottom'] = '';
        }
        return $this->output($result, self::STATUS_OK);
    }

    /**
     * Check if MetaLib databases are searchable.
     *
     * @return \Zend\Http\Response
     */
    public function metalibLinksAjax()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('MetaLib');
        if (!isset($config->General->enabled) || !$config->General->enabled) {
            throw new \Exception('MetaLib is not enabled');
        }

        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        $authorized = $auth->isGranted('finna.authorized');
        $query = new Query();
        $metalib = $this->getServiceLocator()->get('VuFind\Search');

        $results = [];
        $ids = $this->getRequest()->getQuery()->get('id');
        foreach ($ids as $id) {
            $backendParams = new ParamBag();
            $backendParams->add('irdInfo', [$id]);
            $result
                = $metalib->search('MetaLib', $query, false, false, $backendParams);
            $info = $result->getIRDInfo();

            $status = null;
            if ($info
                && ($authorized || strcasecmp($info['access'], 'guest') == 0)
            ) {
                $status = $info['searchable'] ? 'allowed' : 'nonsearchable';
            } else {
                $status = 'denied';
            }
            $results = ['id' => $id, 'status' => $status];
        }

        return $this->output($results, self::STATUS_OK);
    }

    /**
     * Register online paid fines to the ILS.
     *
     * @return \Zend\Http\Response
     */
    public function registerOnlinePaymentAction()
    {
        $this->outputMode = 'json';
        $params = $this->getRequest()->getPost()->toArray();
        $res = $this->processPayment($params);
        $returnUrl = $this->url()->fromRoute('myresearch-fines');
        return $res['success']
            ? $this->output($returnUrl, self::STATUS_OK)
            : $this->output($returnUrl, self::STATUS_ERROR, 500);
    }

    /**
     * Handle Paytrail notification request.
     *
     * @return void
     */
    public function paytrailNotifyAction()
    {
        $params = $this->getRequest()->getQuery()->toArray();
        $this->processPayment($params);
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
        $this->setLogger($this->getServiceLocator()->get('VuFind\Logger'));
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');

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
        $httpService = $this->getServiceLocator()->get('VuFind\Http');
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
            if (substr($item['label'], 0, 1) == '(') {
                // Ignore searches that begin with a parenthesis
                // because they are likely to be advanced searches
                continue;
            } else if ($item['label'] === '-') {
                // Ignore empty searches
                continue;
            } else {
                $label = $item['label'];
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
        if ($type = $this->getBrowseAction($this->getRequest())) {
            $config
                = $this->getServiceLocator()->get('VuFind\Config')->get('browse');

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
            $facetHelper = $this->getServiceLocator()
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
     * Convert XML to array for bX recommendations
     *
     * @param \simpleXMLElement $xml XML to convert
     * @param string            $ns  Optional namespace for nodes
     *
     * @return array
     */
    protected function convertToArray($xml, $ns = '')
    {
        $result = [];
        foreach ($xml->children($ns) as $node) {
            $children = $node->children($ns);
            if (count($children) > 0) {
                $item = $this->convertToArray($node, $ns);
            } else {
                $item = (string)$node;
            }
            $key = $node->getName();
            if (isset($result[$key])) {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $item;
            } else {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /**
     * Create OpenURL for a bX recommendation
     *
     * @param array $item Recommendation fields
     *
     * @return string
     */
    protected function createBxOpenUrl($item)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        if (!isset($config->OpenURL['url'])) {
            return '';
        }

        if (!empty($this->config->OpenURL['rfr_id'])) {
            $coinsID = $this->config->OpenURL['rfr_id'];
        } elseif (!empty($this->config->COINS['identifier'])) {
            $coinsID = $this->config->COINS['identifier'];
        } else {
            $coinsID = 'finna.fi';
        }

        $params = [
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'rfr_id' => "info:sid/{$coinsID}:generator",
            'rft_val_fmt' => 'info:ofi/fmt:kev:mtx:journal'
        ];

        foreach ($item as $key => $value) {
            if ($key == 'authors') {
                foreach ($value['author'][0] as $auKey => $auValue) {
                    $params["rft.$auKey"] = $auValue;
                }
            } else {
                $params["rft.$key"] = $value;
            }
        }
        return $config->OpenURL['url'] . '?' . http_build_query($params);
    }

    /**
     * Utility function for extracting an image URL from a HTML snippet.
     *
     * @param string $html HTML snippet.
     *
     * @return mixed null|string
     */
    protected function extractImage($html)
    {
        if (empty($html)) {
            return null;
        }
        $doc = new \DOMDocument();
        // Silence errors caused by invalid HTML
        libxml_use_internal_errors(true);
        if (!$doc->loadHTML($html)) {
            return null;
        }
        libxml_clear_errors();

        $img = null;
        $imgs = iterator_to_array($doc->getElementsByTagName('img'));
        if (!empty($imgs)) {
            $img = $imgs[0];
        }

        return $img ? $img->getAttribute('src') : null;
    }

    /**
     * Return facet data (labels, counts, min/max values) for a search.
     * Used by dateRangeVisualAjax.
     *
     * @param boolean $solr  Solr search?
     * @param string  $field Index field to be used in faceting
     * @param string  $facet Facet
     * @param string  $query Search query
     *
     * @return array
     */
    protected function getFacetList($solr, $field, $facet, $query = false)
    {
        $results = $this->getResultsManager()->get($solr ? 'Solr' : 'Primo');
        $params = $results->getParams();

        if (!$query) {
            $query = $this->getRequest()->getQuery();
        }
        $params->addFacet($field);
        $params->initFromRequest($query);

        if ($solr) {
            $facets = $results->getFullFieldFacets(
                [$facet], false, -1, 'count'
            );
            $facetList = $facets[$facet]['data']['list'];
        } else {
            $results->performAndProcessSearch();
            $facets = $results->getFacetlist([$facet => $facet]);
            $facetList = $facets[$facet]['list'];
        }

        return $facetList;
    }
}
