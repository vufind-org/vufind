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
use VuFind\Exception\RecordMissing as RecordMissingException;

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
     * Gathers details on a file based on the id
     *
     * @param string $id       record id
     * @param bool   $skipSolr Should we skip accessing the Solr index for details?
     *
     * @return associative array
     */
    protected function getDetails($id, $skipSolr = false)
    {
        $record = false;
        if (!$skipSolr) {
            try {
                $record = $this->getSolrDetails($id);
            } catch (RecordMissingException $e) {
                // Do nothing, handled in fedora below
            }
        }
        if (!$record) {
            $record = $this->getFedora()->getRecordDetails($id);
        }
        if (empty($record)) {
            throw new RecordMissingException('Record not found.');
        }
        $details = $this->formatDetails($record);
        return $details;
    }

    /**
     * Get details from Solr
     *
     * @param string $id ID to look up
     *
     * @return array
     * @throws \Exception
     */
    protected function getSolrDetails($id)
    {
        // Blow up now if we can't retrieve the record:
        if ($record = $this->getRecordLoader()->load($id)->getRawData()) {
            return $record;
        } else {
            throw new RecordMissingException('Solr details unavailable');
        }
    }
    
    /**
     * Organize the details based on config
     *
     * @param string $record associative array (fieldname => value)
     *
     * @return array
     * @throws \Exception
     */
    protected function formatDetails($record)
    {
        // Get config for which details we want
        $fields = $combinedFields = array(); // Save to combine later
        $detailsList = $this->getDetailsList();
        if (empty($detailsList)) {
            throw new \Exception('Missing [Details] in VuDL.ini');
        }
        foreach ($detailsList as $key=>$title) {
            $keys = explode(',', $key);
            foreach ($keys as $k) {
                $fields[$k] = $title;
            }
            // Link up to top combined field
            if (count($keys) > 1) {
                $combinedFields[] = $keys;
            }
        }

        // Pool details
        $details = array();
        foreach ($fields as $key=>$title) {
            if (isset($record[$key])) {
                $details[$key] = array('title' => $title, 'value' => $record[$key]);
            }
        }

        // Rearrange combined fields
        foreach ($combinedFields as $fields) {
            $main = $fields[0];
            for ($i=1;$i<count($fields);$i++) {
                if (isset($details[$fields[$i]])) {
                    if (!is_array($details[$main]['value'])) {
                        $details[$main]['value'] = array($details[$main]['value']);
                    }
                    $details[$main]['value'][] = $details[$fields[$i]]['value'];
                }
            }
        }
        return $details;
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
        $parents = $this->getFedora()->getParentList($id);
        foreach ($parents[0] as $i=>$parent) {
            if (in_array('ResourceCollection', $this->getFedora()->getClasses($i))) {
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
        $data = $this->getFedora()->getStructmap($parent);
        $lists = array();
        preg_match_all('/vudl:[^"]+/', $data, $lists);
        // GET LIST ITEMS
        foreach ($lists[0] as $list=>$list_id) {
            $data = $this->getFedora()->getStructmap($list_id);
            $items = array();
            preg_match_all('/vudl:[^"]+/', $data, $items);
            foreach ($items[0] as $i=>$id) {
                if ($id == $child) {
                    return array($list, $i);
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
            $this->getFedora(), $this->url(), $this->getVuDLRoutes(),
            $cache ? $this->getCache() : false
        );
        return $generator->getOutline($root, $start, $pageLength);
    }

    /**
     * Ajax function for the VuDL view
     * Return JSON encoding of pages
     *
     * @return json string of pages
     */
    public function ajaxAction()
    {
        $method =(String) $this->params()->fromQuery('method');
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
        $output = array('data'=>$data, 'status'=>'OK');
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
        $data = array(
            'outline' => $this->getOutline($id, $start, $end-$start),
            'start'  => (int)$start
        );
        $data['outline'] = $data['outline']['lists'][0];
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
        $data['techinfo'] = $this->getTechInfo($data);
        $data['keys'] = array_keys($data);
        try {
            $view = $renderer->render(
                'vudl/views/'.$data['filetype'].'.phtml',
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
     * Get collapsable XML for an id
     *
     * @param object $record Record data
     *
     * @return html string
     */
    public function getTechInfo($record = null)
    {
        if ($record == null) {
            $record = $this->params()->fromPost();
        }
        if ($record == null) {
            $id = $this->params()->fromQuery('id');
            $list = array();
            preg_match_all(
                '/dsid="([^"]+)"/',
                strtolower($this->getFedora()->getDatastreams($id)),
                $list
            );
            $record = array_flip($list[1]);
            $record['id'] = $id;
        }

        $ret = array();

        // OCR
        if (isset($record['ocr-dirty'])) {
            $record['ocr-dirty'] = $this->getFedora()
                ->getDatastreamContent($record['id'], 'OCR-DIRTY');
        }
        // Technical Information
        if (isset($record['master-md'])) {
            $record['techinfo'] = $this->getFedora()
                ->getDatastreamContent($record['id'], 'MASTER-MD');
            $ret += $this->getSizeAndTypeInfo($record['techinfo']);
        }
        $renderer = $this->getViewRenderer();
        $ret['div'] = $renderer
            ->render('vudl/techinfo.phtml', array('record'=>$record));
        return $ret;
    }

    /**
     * Get size/type information out of the technical metadata.
     *
     * @param string $techInfo Technical metadata
     *
     * @return array
     */
    protected function getSizeAndTypeInfo($techInfo)
    {
        $data = $type = array();
        preg_match('/<size[^>]*>([^<]*)/', $techInfo, $data);
        preg_match('/mimetype="([^"]*)/', $techInfo, $type);
        $size_index = 0;
        if (count($data) > 1) {
            $bytes = intval($data[1]);
            $sizes = array('bytes','KB','MB');
            while ($size_index < count($sizes)-1 && $bytes > 1024) {
                $bytes /= 1024;
                $size_index++;
            }
            return array(
                'size' => round($bytes, 1) . ' ' . $sizes[$size_index],
                'type' => $type[1]
            );
        }
        return array();
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

        $classes = $this->getFedora()->getClasses($id);
        if (in_array('FolderCollection', $classes)) {
            return $this->forwardTo('Collection', 'Home', array('id'=>$id));
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
            if ($driver->isProtected()) {
                die('Access Denied.');
            }
        } catch(\Exception $e) {
        }

        // File information / description
        $fileDetails = $this->getDetails($root);

        // Copyright information
        $check = $this->getFedora()->getDatastreamHeaders($root, 'LICENSE');
        if (!strpos($check[0], '404')) {
            $xml = $this->getFedora()->getDatastreamContent($root, 'LICENSE');
            preg_match('/xlink:href="(.*?)"/', $xml, $license);
            $fileDetails['license'] = $license[1];
            $fileDetails['special_license'] = false;
            $licenseValues = $this->getLicenses();
            foreach ($licenseValues as $tell=>$value) {
                if (strpos($fileDetails['license'], $tell)) {
                    $fileDetails['special_license'] = $value;
                    break;
                }
            }
        }
        $view->details = $fileDetails;

        // Get ids for all files
        $outline = $this->getOutline(
            $root,
            max(0, $view->initPage-($this->getFedora()->getPageLength()/2))
        );

        // Send the data for the first pages
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->outline = $outline;
        $parents = $this->getFedora()->getParentList($root);
        //$keys = array_keys($parents);
        //$view->hierarchyID = end($keys);
        $view->parents = $parents;
        if ($id != $root) {
            $view->parentID = $root;
            $view->breadcrumbEnd = $outline['lists'][0][$view->page]['label'];
        }
        $view->pagelength = $this->getFedora()->getPageLength();
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
        $data =$this->getFedora()->getMemberList($this->getRootId());
        $outline = array();
        foreach ($data as $item) {
            $outline[] = array(
                'id' => $item['id'],
                'img' => $this->url()->fromRoute(
                    'files',
                    array(
                        'id'=>(String)$item['id'],
                        'type'=>'THUMBNAIL'
                    )
                ),
                'label' => $item['title'] //(String)$item->memberTitle
            );
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
        $fileDetails = $this->getDetails($root);
        $view->details = $fileDetails;

        // Get ids for all files
        $outline = $this->getOutline($root, 0);

        // Send the data for the first pages
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->outline = $outline;
        $parents = $this->getFedora()->getParentList($root);
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
        $queries = array(
            'modeltype_str_mv:"vudl-system:FolderCollection"',
            'modeltype_str_mv:"vudl-system:ResourceCollection"',
            // TODO: make these work:
            //'modeltype_str_mv:"vudl-system:ImageData"',
            //'modeltype_str_mv:"vudl-system:PDFData"',
        );
        $response = '';
        foreach ($queries as $q) {
            $params = new \VuFindSearch\ParamBag(array('q'=>$q));
            $response .= $connector->search($params);
        }
        $result = array();
        preg_match_all('/numFound="([^"]*)"/', $response, $result);
        $view->totals = array(
            'folders'=>intval($result[1][0]),
            'resources'=>intval($result[1][1]),
            // TODO: make these work:
            //'images'=>intval($result[1][2]),
            //'pdfs'=>intval($result[1][3])
        );
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
     * Redirect to the appropriate sibling.
     *
     * @return mixed
     */
    protected function siblingAction()
    {
        $params = $this->params()->fromQuery();
        $members = $data = array();
        preg_match_all(
            '/div ORDER="([^"]*)[^<]*<[^<]*"(vudl:[^"]*)/i',
            $this->getFedora()->getStructmap($params['trail']), $data
        );
        for ($i=0;$i<count($data[0]);$i++) {
            $members[intval($data[1][$i])-1] = $data[2][$i];
        }
        if (count($members) < 2) {
            return $this->redirect()
                ->toRoute('Collection', 'Home', array('id'=>$params['trail']));
        }
        $index = -1;
        foreach ($members as $i=>$member) {
            if ($member == $params['id']) {
                $index = $i + count($members);
                break;
            }
        }
        if ($index == -1) {
            return $this->redirect()
                ->toRoute('collection', array('id'=>$params['trail']));
        } elseif (isset($params['prev_x'])) {
            return $this->redirect()->toRoute(
                'vudl-record', array('id'=>$members[($index-1)%count($members)])
            );
        } else {
            return $this->redirect()->toRoute(
                'vudl-record', array('id'=>$members[($index+1)%count($members)])
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
            ->forwardTo('Collection', 'Home', array('id' => $this->getRootId()));
    }
}
