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
        $publicList = $this->params()->fromQuery('public-list') == '1';

        list($source, $recId) = explode('.', $id, 2);
        if ($source == 'pci') {
            $source = 'Primo';
        } else {
            $source = 'Solr';
        }
        $driver = $this->getRecordLoader()->load($id, $source);

        $view = $this->createViewModel(array());
        $view->setTemplate('RecordDriver/SolrDefault/record-image-popup.phtml');
        $view->setTerminal(true);
        $view->driver = $driver;
        $view->index = $index;

        $user = $this->getUser();
        if ($data = $user->getSavedData($id, null)) {
            $notes = [];
            foreach ($data as $list) {
                if ($list->notes && $list->notes != '') {
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

}
