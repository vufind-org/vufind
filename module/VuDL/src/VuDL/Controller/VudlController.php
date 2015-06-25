<?php
/**
 * VuDLController Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   David Lacy <david.lacy@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuDL\Controller;
use VuFind\Exception\RecordMissing as RecordMissingException,
    VuFindSearch\ParamBag;

/**
 * This controller is for the viewing of the digital library files.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VudlController extends AbstractVuDL
{
    /**
     * Retrieve the object cache.
     *
     * @return object
     */
    protected function getCache()
    {
        return $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('object');
    }

    /**
     * Returns the root id for any parent this item may have
     * ie. If we're requesting a specific page, return the book
     *
     * @param string $id record id
     *
     * @return string $id
     */
    protected function getRoot($id)
    {
        $parents = array_reverse($this->getConnector()->getParentList($id));
        foreach (array_keys($parents[0]) as $i) {
            if (in_array(
                'ResourceCollection',
                $this->getConnector()->getClasses($i)
            )) {
                return $i;
            }
        }
        return $id;
    }

    /**
     * Returns the page number of the child in a parent
     *
     * @param string $parent parent record id
     * @param string $child  child record id
     *
     * @return string $id
     */
    protected function getPage($parent, $child)
    {
        // GET LISTS
        $lists = $this->getConnector()->getOrderedMembers($parent);
        // GET LIST ITEMS
        foreach ($lists as $list => $list_data) {
            $items = $this->getConnector()->getOrderedMembers($list_data);
            foreach ($items as $i => $id) {
                if ($id == $child) {
                    return [$list, $i];
                }
            }
        }
    }

    /**
     * Generate an array of all child pages and their information/images
     *
     * @param string $root       record id to search under
     * @param string $start      page/doc to start with for the return
     * @param int    $pageLength page length (leave null to use default)
     *
     * @return associative array of the lists with their members
     */
    protected function getOutline($root, $start = 0, $pageLength = null)
    {
        $cache = (strtolower($this->params()->fromQuery('cache')) == 'no');

        $generator = new \VuDL\OutlineGenerator(
            $this->getConnector(), $this->url(), $this->getVuDLRoutes(),
            $cache ? $this->getCache() : false
        );
        return $generator->getOutline($root, $start, $pageLength);
    }

    /**
     * Get the technical metadata for a record from POST
     *
     * @return array
     */
    protected function getTechInfo()
    {
        return $this->getConnector()->getTechInfo(
            $this->params()->fromPost(),
            $this->getViewRenderer()
        );
    }

    /**
     * Ajax function for the VuDL view
     * Return JSON encoding of pages
     *
     * @return json string of pages
     */
    public function ajaxAction()
    {
        $method = (String) $this->params()->fromQuery('method');
        return $this->jsonReturn($this->$method());
    }

    /**
     * Format data for return as JSON
     *
     * @param string $data Data to be encoded
     *
     * @return json string
     */
    protected function jsonReturn($data)
    {
        $output = ['data' => $data, 'status' => 'OK'];
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type', 'application/javascript'
        );
        $headers->addHeaderLine(
            'Cache-Control', 'no-cache, must-revalidate'
        );
        $headers->addHeaderLine(
            'Expires', 'Mon, 26 Jul 1997 05:00:00 GMT'
        );
        $response->setContent(json_encode($output));
        return $response;
    }

    /**
     * Returns the outline for the next offset block of records
     *
     * @return associative array
     */
    public function pageAjax()
    {
        $id = $this->params()->fromQuery('record');
        $start = $this->params()->fromQuery('start');
        $end = $this->params()->fromQuery('end');
        $data = [
            'outline' => $this->getOutline($id, $start, $end - $start),
            'start'  => (int)$start
        ];
        $data['outline'] = current($data['outline']['lists']);
        if (isset($data['outline'])) {
            $data['length'] = count($data['outline']);
        } else {
            $data['length'] = 0;
        }
        return $data;
    }

    /**
     * Template view swapping, this function returns the html from the template
     *
     * @return json string of page
     */
    public function viewLoad()
    {
        $renderer = $this->getViewRenderer();
        $data = $this->params()->fromPost();
        if ($data == null) {
            $id = $this->params()->fromQuery('id');
            $list = [];
            preg_match_all(
                '/dsid="([^"]+)"/',
                strtolower($this->getConnector()->getDatastreams($id)),
                $list
            );
            $data = array_flip($list[1]);
            $data['id'] = $id;
        }
        $data['keys'] = array_keys($data);
        try {
            $view = $renderer->render(
                'vudl/views/' . $data['filetype'] . '.phtml',
                $data
            );
        } catch(\Exception $e) {
            $view = $renderer->render(
                'vudl/views/download.phtml',
                $data
            );
        }
        return $view;
    }

    /**
     * Display record in VuDL from Fedora
     *
     * @return View Object
     */
    public function recordAction()
    {
        // Target id
        $id = $this->params()->fromRoute('id');
        if ($id == null) {
            return $this->forwardTo('VuDL', 'Home');
        }

        $classes = $this->getConnector()->getClasses($id);
        if (in_array('FolderCollection', $classes)) {
            return $this->forwardTo('Collection', 'Home', ['id' => $id]);
        }
        $view = $this->createViewModel();

        // Check if we're a ResourceObject || find parent
        $root = $this->getRoot($id);
        list($currList, $currPage) = $this->getPage($root, $id);
        $view->initPage = $root == $id ? 0 : $currPage;
        $view->initList = $currList ?: 0;
        $view->id = $root;

        try {
            $driver = $this->getRecordLoader()->load($root, 'VuFind');
        } catch(\Exception $e) {
        }
        if (isset($driver) && $driver->isProtected()) {
            return $this->forwardTo('VuDL', 'Denied', ['id' => $id]);
        }

        // File information / description
        $fileDetails = $this->getConnector()->getDetails($root, true);

        // Copyright information
        list($fileDetails['license'], $fileDetails['special_license'])
            = $this->getConnector()->getCopyright($root, $this->getLicenses());

        $view->details = $fileDetails;

        // Get ids for all files
        $outline = $this->getOutline(
            $root,
            max(0, $view->initPage - ($this->getConnector()->getPageLength() / 2))
        );

        // Send the data for the first pages
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->outline = $outline;
        $parents = $this->getConnector()->getParentList($root);
        //$keys = array_keys($parents);
        //$view->hierarchyID = end($keys);
        $view->parents = $parents;
        if ($id != $root) {
            $view->parentID = $root;
        }
        $view->pagelength = $this->getConnector()->getPageLength();
        return $view;
    }

    /**
     * Forward home action to browse
     *
     * @return forward
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $config = $this->getConfig('vudl');
        $children = $this->getConnector()->getMemberList($config->General->root_id);
        foreach ($children as $item) {
            $outline[] = [
                'id' => $item['id'],
                'img' => $this->url()->fromRoute(
                    'files',
                    [
                        'id'   => $item['id'],
                        'type' => 'THUMBNAIL'
                    ]
                ),
                'label' => $item['title'][0]
            ];
        }
        $view->thumbnails = $outline;
        return $view;
    }

    /**
     * Display record in VuDL from Fedora as a grid
     *
     * @return View Object
     */
    public function gridAction()
    {
        $view = $this->createViewModel();
        // Target id
        $id = $this->params()->fromRoute('id');

        // Check if we're a ResourceObject || find parent
        $root = $this->getRoot($id);
        $view->page = $root == $id ? 0 : $this->getPage($root, $id);
        $view->id = $root;

        // File information / description
        $fileDetails = $this->getConnector()->getDetails($root, true);
        $view->details = $fileDetails;

        // Get ids for all files
        $outline = $this->getOutline($root, 0);

        // Send the data for the first pages
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->outline = $outline;
        $parents = $this->getConnector()->getParentList($root);
        //$keys = array_keys($parents);
        //$view->hierarchyID = end($keys);
        $view->parents = $parents;
        return $view;
    }

    /**
     * About page
     *
     * @return View Object
     */
    public function aboutAction()
    {
        $view = $this->createViewModel();
        $connector = $this->getServiceLocator()->get('VuFind\Search\BackendManager')
            ->get('Solr')->getConnector();
        $queries = [
            'modeltype_str_mv:"vudl-system:FolderCollection"',
            'modeltype_str_mv:"vudl-system:ResourceCollection"',
            // TODO: make these work:
            //'modeltype_str_mv:"vudl-system:ImageData"',
            //'modeltype_str_mv:"vudl-system:PDFData"',
        ];
        $response = '';
        foreach ($queries as $q) {
            $params = new \VuFindSearch\ParamBag(['q' => $q]);
            $response .= $connector->search($params);
        }
        $result = [];
        preg_match_all('/"ngroups">([^<]*)/', $response, $result);
        $view->totals = [
            'folders' => intval($result[1][0]),
            'resources' => intval($result[1][1]),
            // TODO: make these work:
            //'images'=>intval($result[1][2]),
            //'pdfs'=>intval($result[1][3])
        ];
        return $view;
    }

    /**
     * Copyright page
     *
     * @return View Object
     */
    public function copyrightAction()
    {
        $view = $this->createViewModel();
        return $view;
    }

    /**
     * Access denied screen.
     *
     * @return mixed
     */
    protected function deniedAction()
    {
        $view = $this->createViewModel();
        $view->id = $this->params()->fromRoute('id');
        return $view;
    }

    /**
     * Redirect to the appropriate sibling.
     *
     * @return mixed
     */
    protected function siblingAction()
    {
        $params = $this->params()->fromQuery();
        $members = $this->getConnector()->getOrderedMembers($params['trail']);
        if (count($members) < 2) {
            //return $this->redirect()
                //->toRoute('Collection', 'Home', array('id'=>$params['trail']));
        }
        $index = -1;
        foreach ($members as $i => $member) {
            if ($member == $params['id']) {
                $index = $i + count($members);
                break;
            }
        }
        if ($index == -1) {
            return $this->redirect()
                ->toRoute('collection', ['id' => $params['trail']]);
        } elseif (isset($params['prev'])) {
            return $this->redirect()->toRoute(
                'vudl-record', ['id' => $members[($index - 1) % count($members)]]
            );
        } else {
            return $this->redirect()->toRoute(
                'vudl-record', ['id' => $members[($index + 1) % count($members)]]
            );
        }
    }

    /**
     * Redirect to the appropriate sibling.
     *
     * @return View Object
     */
    protected function collectionsAction()
    {
        return $this
            ->forwardTo('Collection', 'Home', ['id' => $this->getRootId()]);
    }
}
