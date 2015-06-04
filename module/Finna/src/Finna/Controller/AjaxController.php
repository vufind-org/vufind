<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace Finna\Controller;
use Zend\Cache\StorageFactory,
    Zend\Feed\Reader\Reader;

/**
 * This controller handles Finna AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{
    /**
     * Check Requests are Valid
     *
     * @return \Zend\Http\Response
     */
    protected function checkRequestsAreValidAjax()
    {
        $this->writeSession();  // avoid session write timing bug
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
            $this->translate('An error has occurred'), self::STATUS_ERROR
        );
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
     * Return record description in JSON format.
     *
     * @return mixed \Zend\Http\Response
     */
    public function getDescriptionAjax()
    {
        if (!$id = $this->params()->fromQuery('id')) {
            return $this->output('', self::STATUS_ERROR);
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
                return $this->output('', self::STATUS_ERROR);
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
        return $this->output('', self::STATUS_ERROR);
    }

    /**
     * Return rendered HTML for my lists navigation.
     *
     * @return mixed \Zend\Http\Response
     */
    public function getMyListsAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
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
     * Fetch Links from resolver given an OpenURL and format as HTML
     * and output the HTML content in JSON object.
     *
     * @return \Zend\Http\Response
     * @author Graham Seaman <Graham.Seaman@rhul.ac.uk>
     */
    protected function getResolverLinksAjax()
    {
        $this->writeSession();  // avoid session write timing bug
        $openUrl = $this->params()->fromQuery('openurl', '');

        $config = $this->getConfig();
        $resolverType = isset($config->OpenURL->resolver)
            ? $config->OpenURL->resolver : 'other';
        $pluginManager = $this->getServiceLocator()
            ->get('VuFind\ResolverDriverPluginManager');
        if (!$pluginManager->has($resolverType)) {
            return $this->output(
                $this->translate("Could not load driver for $resolverType"),
                self::STATUS_ERROR
            );
        }
        $resolver = new \VuFind\Resolver\Connection(
            $pluginManager->get($resolverType)
        );
        if (isset($config->OpenURL->resolver_cache)) {
            $resolver->enableCache($config->OpenURL->resolver_cache);
        }
        $result = $resolver->fetchLinks($openUrl);

        // Sort the returned links into categories based on service type:
        $electronic = $print = $services = [];
        foreach ($result as $link) {
            switch (isset($link['service_type']) ? $link['service_type'] : '') {
            case 'getHolding':
                $print[] = $link;
                break;
            case 'getWebService':
                $services[] = $link;
                break;
            case 'getDOI':
                // Special case -- modify DOI text for special display:
                $link['title'] = $this->translate('Get full text');
                $link['coverage'] = '';
            case 'getFullTxt':
            default:
                $electronic[] = $link;
                break;
            }
        }

        // Get the OpenURL base:
        if (isset($config->OpenURL) && isset($config->OpenURL->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            list($base) = explode('?', $config->OpenURL->url);
        } else {
            $base = false;
        }

        // Render the links using the view:
        $view = [
            'openUrlBase' => $base, 'openUrl' => $openUrl, 'print' => $print,
            'electronic' => $electronic, 'services' => $services,
            'searchClassId' => $this->params()->fromQuery('searchClassId', '')
        ];
        $html = $this->getViewRenderer()->render('ajax/resolverLinks.phtml', $view);

        // output HTML encoded in JSON object
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Update or create a list object.
     *
     * @return mixed \Zend\Http\Response
     */
    public function editListAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR);
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
        $required = ['id', 'title'];
        foreach ($required as $param) {
            if (!isset($params[$param])) {
                return $this->output(
                    "Missing parameter '$param'", self::STATUS_ERROR
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
     * @return mixed \Zend\Http\Response
     */
    public function editListResourceAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR);
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
                    "Missing parameter '$param'", self::STATUS_ERROR
                );
            }
        }

        list($source, $id) = explode('.', $params['id'], 2);
        $source = $source === 'pci' ? 'Primo' : 'VuFind';

        $listId = $params['listId'];
        $notes = $params['notes'];

        $resources = $user->getSavedData($params['id'], $listId, $source);
        if (empty($resources)) {
            return $this->output("User resource not found", self::STATUS_ERROR);
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
     * Add resources to a list.
     *
     * @return mixed \Zend\Http\Response
     */
    public function addToListAjax()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            return $this->output('Lists disabled', self::STATUS_ERROR);
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
                    "Missing parameter '$param'", self::STATUS_ERROR
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
                "Invalid list id", self::STATUS_ERROR
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
                    $this->translate('Failed'),
                    self::STATUS_ERROR
                );
            }
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
            return $this->output(false, self::STATUS_ERROR);
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
     * Return feed content and settings in JSON format.
     *
     * @return mixed
     */
    public function getFeedAjax()
    {
        if (!$id = $this->params()->fromQuery('id')) {
            return $this->output('Missing feed id', self::STATUS_ERROR);
        }

        $touchDevice = $this->params()->fromQuery('touch-device') !== null
            ? $this->params()->fromQuery('touch-device') === '1'
            : false
        ;

        $config = $this->getServiceLocator()->get('VuFind\Config')->get('rss');
        if (!isset($config[$id])) {
            return $this->output('Missing feed configuration', self::STATUS_ERROR);
        }

        $config = $config[$id];
        if (!$config->active) {
            return $this->output('Feed inactive', self::STATUS_ERROR);
        }

        if (!$url = $config->url) {
            return $this->output('Missing feed URL', self::STATUS_ERROR);
        }

        $translator = $this->getServiceLocator()->get('VuFind\Translator');
        $language   = $translator->getLocale();
        if (isset($url[$language])) {
            $url = trim($url[$language]);
        } else if (isset($url['*'])) {
            $url = trim($url['*']);
        } else {
            return $this->output('Missing feed URL', self::STATUS_ERROR);
        }

        $type = $config->type;
        $channel = null;

        // Check for cached version
        $cacheEnabled = false;
        $cacheDir = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('feed')->getOptions()->getCacheDir();

        $localFile = "$cacheDir/" . md5(var_export($config, true)) . '.xml';

        $cacheConfig = $this->getServiceLocator()
            ->get('VuFind\Config')->get('config');
        $maxAge = isset($cacheConfig->Content->feedcachetime)
            ? $cacheConfig->Content->feedcachetime : false;

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
                        "File $url could not be found", self::STATUS_ERROR
                    );
                }
                $channel = Reader::importFile($url);
            }
        }

        if (!$channel) {
            return $this->output('Parsing failed', self::STATUS_ERROR);
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
                            $tmp= new \DateTime(($tmp['date']));
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

        // Search for <a> elements with <img> children;
        // they are likely links to full-size images
        if ($links = iterator_to_array($doc->getElementsByTagName('a'))) {
            foreach ($links as $link) {
                foreach ($link->childNodes as $child) {
                    if ($child->nodeName == 'img') {
                        $img = $child;
                        break;
                    }
                }
            }
        }

        // Not found, return first <img> element if available
        if (!$img) {
            $imgs = iterator_to_array($doc->getElementsByTagName('img'));
            if (!empty($imgs)) {
                $img = $imgs[0];
            }
        }
        return $img ? $img->getAttribute('src') : null;
    }
}
