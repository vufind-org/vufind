<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{
    use OnlinePaymentControllerTrait,
        SearchControllerTrait,
        CatalogLoginTrait;

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

        $table = $this->serviceLocator->get('VuFind\DbTablePluginManager')
            ->get('UserList');
        $list = $table->getExisting($listId);
        if ($list->user_id !== $user->id) {
            return $this->output(
                "Invalid list id", self::STATUS_ERROR, 400
            );
        }

        $favorites = $this->serviceLocator
            ->get('VuFind\Favorites\FavoritesService');
        foreach ($ids as $id) {
            $source = $id[0];
            $recId = $id[1];
            try {
                $driver = $this->getRecordLoader()->load($recId, $source);
                $favorites->save(['list' => $listId], $user, $driver);
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
            if (empty($params[$param])) {
                return $this->output(
                    "Missing parameter '$param'", self::STATUS_ERROR, 400
                );
            }
        }
        $id = $params['id'];

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $table = $this->serviceLocator->get('VuFind\DbTablePluginManager')
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
        $map = ['pci' => 'Primo'];
        $source = isset($map[$source]) ? $map[$source] : DEFAULT_SEARCH_BACKEND;

        $listId = $params['listId'];
        $notes = $params['notes'];

        $resources = $user->getSavedData($params['id'], $listId, $source);
        if (empty($resources)) {
            return $this->output("User resource not found", self::STATUS_ERROR, 400);
        }

        $table = $this->serviceLocator->get('VuFind\DbTablePluginManager')
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
                $result = $catalog->checkFunction('changePickupLocation');
                if (!$result) {
                    return $this->output(
                        $this->translate('unavailable'),
                        self::STATUS_ERROR,
                        400
                    );
                }

                $details = [
                    'requestId'    => $requestId,
                    'pickupLocationId' => $pickupLocationId
                ];
                $results = $catalog->changePickupLocation($patron, $details);

                return $this->output($results, self::STATUS_OK);
            }
        } catch (\Exception $e) {
            $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
            $this->logError('changePickupLocation failed: ' . $e->getMessage());
            // Fall through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Change request status
     *
     * @return \Zend\Http\Response
     */
    public function changeRequestStatusAjax()
    {
        $requestId = $this->params()->fromQuery('requestId');
        $frozen = $this->params()->fromQuery('frozen');
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
                $result = $catalog->checkFunction('changeRequestStatus');
                if (!$result) {
                    return $this->output(
                        $this->translate('unavailable'),
                        self::STATUS_ERROR,
                        400
                    );
                }

                $details = [
                    'requestId' => $requestId,
                    'frozen' => $frozen
                ];
                $results = $catalog->changeRequestStatus($patron, $details);

                return $this->output($results, self::STATUS_OK);
            }
        } catch (\Exception $e) {
            $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
            $this->logError('changeRequestStatus failed: ' . $e->getMessage());
            // Fall through to the error message below.
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

                            if (is_array($result)) {
                                $msg = $result['status'];
                                $result = $result['valid'];
                            } else {
                                $msg = $result
                                    ? 'ill_request_place_text'
                                    : 'ill_request_error_blocked';
                            }
                            break;
                        case 'StorageRetrievalRequest':
                            $result = $catalog->checkStorageRetrievalRequestIsValid(
                                $id, $item, $patron
                            );

                            if (is_array($result)) {
                                $msg = $result['status'];
                                $result = $result['valid'];
                            } else {
                                $msg = $result
                                    ? 'storage_retrieval_request_place_text'
                                    : 'storage_retrieval_request_error_blocked';
                            }
                            break;
                        default:
                            $result = $catalog->checkRequestIsValid(
                                $id, $item, $patron
                            );

                            if (is_array($result)) {
                                $msg = $result['status'];
                                $result = $result['valid'];
                            } else {
                                $msg = $result
                                    ? 'request_place_text'
                                    : 'hold_error_blocked';
                            }
                            break;
                        }
                        $results[] = [
                            'status' => $result,
                            'msg' => $this->translate($msg)
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
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            return $this->output(
                $this->translate('Comments disabled'),
                self::STATUS_ERROR,
                403
            );
        }

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
        $runner = $this->serviceLocator->get('VuFind\SearchRunner');
        $results = $runner->run(
            ['lookfor' => 'local_ids_str_mv:"' . addcslashes($id, '"') . '"'],
            'Solr',
            function ($runner, $params, $searchId) {
                $params->setLimit(100);
                $params->setPage(1);
                $params->resetFacetConfig();
                $options = $params->getOptions();
                $options->disableHighlighting();
                $options->spellcheckEnabled(false);
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
            = $this->serviceLocator->get('VuFind\Config')->get($configFile);
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
     * Return record description in JSON format.
     *
     * @return \Zend\Http\Response
     */
    public function getDescriptionAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        if (!$id = $this->params()->fromQuery('id')) {
            return $this->output('', self::STATUS_ERROR, 400);
        }

        $cacheDir = $this->serviceLocator->get('VuFind\CacheManager')
            ->getCache('description')->getOptions()->getCacheDir();

        $localFile = "$cacheDir/" . urlencode($id) . '.txt';

        $config = $this->serviceLocator->get('VuFind\Config')->get('config');
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
                $httpService = $this->serviceLocator->get('VuFind\Http');
                $result = $httpService->get($url, [], 60);
                if ($result->isSuccess() && ($content = $result->getBody())) {
                    $encoding = mb_detect_encoding(
                        $content, ['UTF-8', 'ISO-8859-1']
                    );
                    if ('UTF-8' !== $encoding) {
                        $content = utf8_encode($content);
                    }

                    $content = preg_replace('/.*<.B>(.*)/', '\1', $content);
                    $content = strip_tags($content);

                    // Replace line breaks with <br>
                    $content = preg_replace(
                        '/(\r\n|\n|\r){3,}/', '<br><br>', $content
                    );

                    file_put_contents($localFile, $content);

                    return $this->output($content, self::STATUS_OK);
                }
            }
            $language = $this->serviceLocator->get('VuFind\Translator')
                ->getLocale();
            if ($summary = $driver->getSummary($language)) {
                $summary = implode("\n\n", $summary);

                // Replace double hash with a <br>
                $summary = str_replace('##', "\n\n", $summary);

                // Process markdown
                $summary = $this->getViewRenderer()->plugin('markdown')
                    ->toHtml($summary);

                return $this->output($summary, self::STATUS_OK);
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
        $this->disableSessionWrites();  // avoid session write timing bug
        if (null === ($id = $this->params()->fromQuery('id'))) {
            return $this->output('Missing feed id', self::STATUS_ERROR, 400);
        }

        $touchDevice = $this->params()->fromQuery('touch-device') !== null
            ? $this->params()->fromQuery('touch-device') === '1'
            : false
        ;

        $feedService = $this->serviceLocator->get('Finna\Feed');
        try {
            $feed
                = $feedService->readFeed(
                    $id, $this->url(), $this->getServerUrl('home')
                );
        } catch (\Exception $e) {
            return $this->output($e->getMessage(), self::STATUS_ERROR, 400);
        }

        if (!$feed) {
            return $this->output('Error reading feed', self::STATUS_ERROR, 400);
        }

        return $this->output($this->formatFeed($config, $feed), self::STATUS_OK);
    }

    /**
     * Return organisation page feed content and settings in JSON format.
     *
     * @return mixed
     */
    public function getOrganisationPageFeedAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        if (null === ($id = $this->params()->fromQuery('id'))) {
            return $this->handleError('getOrganisationPageFeed: missing feed id');
        }

        if (null === ($url = $this->params()->fromQuery('url'))) {
            return $this->handleError('getOrganisationPageFeed: missing feed url');
        }

        $url = urldecode($url);
        $feedService = $this->serviceLocator->get('Finna\Feed');
        try {
            $config = $this->serviceLocator->get('VuFind\Config')
                ->get('rss-organisation-page');
            $feedConfig = ['url' => $url];

            if (isset($config[$id])) {
                $feedConfig['result'] = $config[$id]->toArray();
            } else {
                $feedConfig['result'] = ['items' => 5];
            }
            $feedConfig['result']['type'] = 'list';
            $feedConfig['result']['active'] = 1;

            $feed
                = $feedService->readFeedFromUrl(
                    $id,
                    $url,
                    $feedConfig,
                    $this->url(), $this->getServerUrl('home')
                );
        } catch (\Exception $e) {
            return $this->handleError(
                "getOrganisationPageFeed: error reading feed from url: {$url}",
                $e->getMessage()
            );
        }

        if (!$feed) {
            return $this->handleError(
                "getOrganisationPageFeed: error reading feed from url: {$url}"
            );
        }

        return $this->output(
            $this->formatFeed($config, $feed, $url), self::STATUS_OK
        );
    }

    /**
     * Utility function for formatting a RSS feed.
     *
     * @param VuFind\Config $config  Feed configuration
     * @param array         $feed    Feed data
     * @param string        $feedUrl Feed URL (needed for organisation page
     * RSS-feeds where the feed URL is passed to the FeedContentController as
     * an URL parameter.
     *
     * @return array Array with keys:
     *   html (string)    Rendered feed content
     *   settings (array) Feed settings
     */
    protected function formatFeed($config, $feed, $feedUrl = false)
    {
        $channel = $feed['channel'];
        $items = $feed['items'];
        $config = $feed['config'];
        $modal = $feed['modal'];

        $images
            = isset($config->content['image'])
            ? $config->content['image'] : true;

        $moreLink = !isset($config->moreLink) || $config->moreLink
             ? $channel->getLink() : null;

        $type = $config->type;
        $linkTo = isset($config->linkTo) ? $config->linkTo : null;

        $key = $touchDevice ? 'touch' : 'desktop';
        $linkText = null;
        if (isset($config->linkText[$key])) {
            $linkText = $config->linkText[$key];
        } elseif (isset($config->linkText) && is_string($config->linkText)) {
            $linkText = $config->linkText;
        }

        $feed = [
            'linkText' => $linkText,
            'moreLink' => $moreLink,
            'type' => $type,
            'items' => $items,
            'touchDevice' => $touchDevice,
            'images' => $images,
            'modal' => $modal,
            'feedUrl' => $feedUrl
        ];

        if (isset($config->title)) {
            if ($config->title == 'rss') {
                $feed['title'] = $channel->getTitle();
            } else {
                $feed['translateTitle'] = $config->title;
            }
        }

        if (isset($config->description)) {
            $feed['description'] = $config->description;
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
        $settings['modal'] = $modal;
        if (isset($config->height)) {
            $settings['height'] = $config->height;
        }

        if ($type == 'carousel' || $type == 'carousel-vertical') {
            $settings['images'] = $images;
            $settings['autoplay']
                = isset($config->autoplay) ? $config->autoplay : false;
            $settings['dots']
                = isset($config->dots) ? $config->dots == true : true;
            $settings['scrollSpeed']
                = isset($config->scrollSpeed) ? $config->scrollSpeed : 750;
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

        return ['html' => $html, 'settings' => $settings];
    }

    /**
     * Return feed full content (from content:encoded tag).
     *
     * @return mixed
     * @throws Exception
     */
    public function getContentFeedAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        if (null === ($id = $this->params()->fromQuery('id'))) {
            return $this->output('Missing feed id', self::STATUS_ERROR, 400);
        }
        $element = urldecode($this->params()->fromQuery('element'));
        if (!$element) {
            $element = 0;
        }
        $feedUrl = $this->params()->fromQuery('feedUrl');
        $feedService = $this->serviceLocator->get('Finna\Feed');
        try {
            if ($feedUrl) {
                $config = $this->getOrganisationFeedConfig($id, $feedUrl);
                $feed = $feedService->readFeedFromUrl(
                    $id, $feedUrl, $config, $this->url(), $this->getServerUrl('home')
                );
            } else {
                $feed = $feedService->readFeed(
                    $id, $this->url(), $this->getServerUrl('home')
                );
            }
        } catch (\Exception $e) {
            return $this->output($e->getMessage(), self::STATUS_ERROR, 400);
        }

        if (!$feed) {
            return $this->output('Error reading feed', self::STATUS_ERROR, 400);
        }

        $channel = $feed['channel'];
        $items = $feed['items'];
        $config = $feed['config'];
        $modal = $feed['modal'];
        $contentPage = $feed['contentPage'] && !$modal;

        $result = ['channel' =>
            ['title' => $channel->getTitle(), 'link' => $channel->getLink()]
        ];
        $numeric = is_numeric($element);
        if ($numeric) {
            $element = (int)$element;
            if (isset($items[$element])) {
                $result['item'] = $items[$element];
            }
        } else {
            foreach ($items as $item) {
                if ($item['id'] === $element) {
                    $result['item'] = $item;
                    break;
                }
            }
        }

        if ($contentPage && !empty($items)) {
            $result['navigation'] = $this->getViewRenderer()->partial(
                'feedcontent/navigation',
                [
                   'items' => $items, 'element' => $element, 'numeric' => $numeric,
                   'feedUrl' => $feedUrl
                ]
            );
        }

        return $this->output($result, self::STATUS_OK);
    }

    /**
     * Return configuration settings for organisation page
     * RSS-feed sections (news, events).
     *
     * @param string $id  Section
     * @param string $url Feed URL
     *
     * @return array settings
     */
    protected function getOrganisationFeedConfig($id, $url)
    {
        $config = $this->serviceLocator->get('VuFind\Config')
            ->get('rss-organisation-page');
        $feedConfig = ['url' => $url];

        if (isset($config[$id])) {
            $feedConfig['result'] = $config[$id]->toArray();
        } else {
            $feedConfig['result'] = ['items' => 5];
        }
        $feedConfig['result']['type'] = 'list';
        $feedConfig['result']['active'] = 1;
        return $feedConfig;
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
        $this->disableSessionWrites();  // avoid session write timing bug

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
     * Return organisation info in JSON format.
     *
     * @return mixed
     */
    public function getOrganisationInfoAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $reqParams = array_merge(
            $this->params()->fromPost(), $this->params()->fromQuery()
        );
        if (empty($reqParams['parent'])) {
            return $this->handleError('getOrganisationInfo: missing parent');
        }
        $parent = is_array($reqParams['parent'])
            ? implode(',', $reqParams['parent']) : $reqParams['parent'];

        if (empty($reqParams['params']['action'])) {
            return $this->handleError('getOrganisationInfo: missing action');
        }
        $params = $reqParams['params'];

        $cookieName = 'organisationInfoId';
        $cookieManager = $this->serviceLocator->get('VuFind\CookieManager');
        $cookie = $cookieManager->get($cookieName);

        $action = $params['action'];
        $buildings = isset($params['buildings'])
            ? explode(',', $params['buildings']) : null;

        $key = $parent;
        if ($action == 'details') {
            if (!isset($params['id'])) {
                return $this->handleError('getOrganisationInfo: missing id');
            }
            if (isset($params['id'])) {
                $id = $params['id'];
                $expire = time() + 365 * 60 * 60 * 24; // 1 year
                $cookieManager->set($cookieName, $id, $expire);
            }
        }

        if (!isset($params['id']) && $cookie) {
            $params['id'] = $cookie;
        }

        if ($action == 'lookup') {
            $link = isset($reqParams['link']) ? $reqParams['link'] : '0';
            $params['link'] = $link === '1';
            $params['parentName'] = isset($reqParams['parentName'])
                ? $reqParams['parentName'] : null;
        }

        $lang = $this->serviceLocator->get('VuFind\Translator')->getLocale();
        $map = ['en-gb' => 'en'];

        if (isset($map[$lang])) {
            $lang = $map[$lang];
        }
        if (!in_array($lang, ['fi', 'sv', 'en'])) {
            $lang = 'fi';
        }

        $service = $this->serviceLocator->get('Finna\OrganisationInfo');
        try {
            $response = $service->query($parent, $params, $buildings);
        } catch (\Exception $e) {
            return $this->handleError(
                'getOrganisationInfo: '
                . "error reading organisation info (parent $parent)",
                $e->getMessage()
            );
        }

        $this->outputMode = 'json';
        return $this->output($response, self::STATUS_OK);
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
     * Retrieve side facets
     *
     * @return \Zend\Http\Response
     */
    public function getSideFacetsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        $rManager = $this->serviceLocator->get('VuFind\RecommendPluginManager');
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

        $runner = $this->serviceLocator->get('VuFind\SearchRunner');
        $results = $runner->run($request, DEFAULT_SEARCH_BACKEND, $setupCallback);

        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
            $this->logError('Solr faceting request failed');
            return $this->output('', self::STATUS_ERROR, 500);
        }

        $recommend = $results->getRecommendations('side');
        $recommend = reset($recommend);

        if (isset($request['enabledFacets'])) {
            // Render requested facets separately
            $response = [];
            $facetConfig = $this->getConfig('facets');
            $facetHelper = $this->serviceLocator
                ->get('VuFind\HierarchicalFacetHelper');
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
            $view = $this->getViewRenderer();
            $view->recommend = $recommend;
            $view->params = $results->getParams();
            $view->searchClassId = 'Solr';
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
                        $response[$facet] = $count;
                        continue 2;
                    }
                }
                if (in_array($facet, $hierarchicalFacets)) {
                    // Return the facet data for hierarchical facets
                    $facetList = $sideFacetSet[$facet]['list'];

                    if (!empty($hierarchicalFacetSortOptions[$facet])) {
                        $facetHelper->sortFacetList(
                            $facetList,
                            'top' === $hierarchicalFacetSortOptions[$facet]
                        );
                    }

                    $facetList = $facetHelper->buildFacetArray(
                        $facet, $facetList, $results->getUrlQuery()
                    );

                    if (!empty($facetConfig->FacetFilters->$facet)
                        || !empty($facetConfig->ExcludeFilters->$facet)
                    ) {
                        $filters = !empty($facetConfig->FacetFilters->$facet)
                            ? $facetConfig->FacetFilters->$facet->toArray()
                            : [];
                        $excludeFilters
                            = !empty($facetConfig->ExcludeFilters->$facet)
                            ? $facetConfig->ExcludeFilters->$facet->toArray()
                            : [];

                        $facetList = $facetHelper->filterFacets(
                            $facetList,
                            $filters,
                            $excludeFilters
                        );
                    }

                    $response[$facet] = $facetList;
                } else {
                    $view->facet = $facet;
                    $view->cluster = isset($sideFacetSet[$facet])
                        ? $sideFacetSet[$facet] : [];
                    $response[$facet]
                        = $view->partial('Recommend/SideFacets/facet.phtml');
                }
            }
            return $this->output($response, self::STATUS_OK);
        } else {
            // Render full sidefacets
            $view = $this->getViewRenderer();
            $view->recommend = $recommend;
            $view->params = $results->getParams();
            $view->searchClassId = 'Solr';
            $html = $view->partial('Recommend/SideFacets.phtml');
            return $this->output($html, self::STATUS_OK);
        }
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

        $recordLoader = $this->serviceLocator->get('VuFind\RecordLoader');
        $similar = $this->serviceLocator->get('VuFind\RelatedPluginManager')
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
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    protected function systemStatusAction()
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
    public function registerOnlinePaymentAction()
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
    public function onlinePaymentNotifyAction()
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
