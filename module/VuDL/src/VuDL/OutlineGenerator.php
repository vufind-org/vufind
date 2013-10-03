<?php
/**
 * VuDL outline generator
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
namespace VuDL;
use Zend\Mvc\Controller\Plugin\Url as UrlHelper;

/**
 * VuDL outline generator
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class OutlineGenerator
{
    /**
     * Fedora connection
     *
     * @var Fedora
     */
    protected $fedora;

    /**
     * URL helper
     *
     * @var UrlHelper
     */
    protected $url;

    /**
     * VuDL route configuration
     *
     * @var array
     */
    protected $routes;

    /**
     * Cache object
     *
     * @var object
     */
    protected $cache;

    /**
     * Constructor
     *
     * @param Fedora      $fedora Fedora connection
     * @param UrlHelper   $url    URL helper
     * @param array       $routes VuDL route configuration
     * @param object|bool $cache  Cache object (or false to disable caching)
     */
    public function __construct(Fedora $fedora, UrlHelper $url, $routes = array(),
        $cache = false
    ) {
        $this->fedora = $fedora;
        $this->url = $url;
        $this->routes = $routes;
        $this->cache = $cache;
    }


    /**
     * Compares the cache date against a given date. If given date is newer,
     * return false in order to refresh cache. Else return cache!
     *
     * @param string                $key     Unique key of cache object
     * @param string|Date           $moddate Date to test cache time freshness
     *
     * @return cache object or false
     */
    protected function getCache($key, $moddate = null)
    {
        if ($this->cache && $cache_item = $this->cache->getItem($key)) {
            if ($moddate == null || (isset($cache_item['moddate'])
                && date_create($cache_item['moddate']) >= date_create($moddate))
            ) {
                return $cache_item['outline'];
            }
        }
        return false;
    }

    /**
     * Save cache object with date to test for freshness
     *
     * @param string                $key   Unique key of cache object
     * @param object                $data  Object to save
     *
     * @return cache object or false
     */
    protected function setCache($key, $data)
    {
        if ($this->cache) {
            $this->cache->setItem(
                $key,
                array(
                    'moddate'=>date(DATE_ATOM),
                    'outline'=>$data
                )
            );
            return $data;
        }
        return false;
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
    public function getOutline($root, $start = 0, $pageLength = null)
    {
        if ($pageLength == null) {
            $pageLength = $this->fedora->getPageLength();
        }
        // Check modification date
        $xml = $this->fedora->getObjectAsXML($root);
        $rootModDate = (string)$xml[0]->objLastModDate;
        // Get lists
        $data = $this->fedora->getStructmap($root);
        $lists = array();
        preg_match_all('/vudl:[^"]+/', $data, $lists);
        $queue = array();
        $moddate = array();
        $outline = array('counts'=>array(), 'names'=>array());
        // Get list items
        foreach ($lists[0] as $i=>$list_id) {
            // Get list name
            $xml = $this->fedora->getObjectAsXML($list_id);
            $outline['names'][] = (String) $xml[0]->objLabel;
            $moddate[$i] = max((string)$xml[0]->objLastModDate, $rootModDate);
            $data = $this->fedora->getStructmap($list_id);
            $list = array();
            preg_match_all('/vudl:[^"]+/', $data, $list);
            $queue[$i] = $list[0];
        }
        $type_templates = array();
        // Get data on all pages and docs
        foreach ($queue as $parent=>$items) {
            $outline['counts'][$parent] = count($items);
            if (count($items) < $start) {
                continue;
            }
            $routes = $this->routes;
            $outline['lists'][$parent] = array();
            for ($i=$start;$i < $start + $pageLength;$i++) {
                if ($i >= count($items)) {
                    break;
                }
                $id = $items[$i];
                // If there's a cache of this page...
                $pageCache = $this->getCache(md5($id), $moddate[$parent]);
                if ($pageCache) {
                    $outline['lists'][$parent][$i] = $pageCache;
                } else {
                    // Else, get all the data and save it to the cache
                    $details = $this->fedora->getRecordDetails($id);
                    $list = array();
                    // Get the file type
                    $file = $this->fedora->getDatastreams($id);
                    preg_match_all(
                        '/dsid="([^"]+)"[^>]*mimeType="([^"]+)/',
                        $file,
                        $list
                    );
                    $masterIndex = array_search('MASTER', $list[1]);
                    $mimetype = $masterIndex ? $list[2][$masterIndex] : 'N/A';
                    if (!$masterIndex) {
                        $type = 'page';
                    } else {
                        $type = substr(
                            $list[2][$masterIndex],
                            strpos($list[2][$masterIndex], '/') + 1
                        );
                    }
                    $item = array(
                        'id' => $id,
                        'fulltype' => $type,
                        'mimetype' => $mimetype,
                        'filetype' => isset($routes[$type])
                            ? $routes[$type]
                            : $type,
                        'label' => isset($details['title'])
                            ? $details['title']
                            : $id,
                        'datastreams' => $list[1],
                        'mimetypes' => $list[2]
                    );
                    $this->setCache(md5($id), $item);
                    $outline['lists'][$parent][$i] = $item;
                }
            }
        }
        foreach ($outline['lists'] as $key=>$list) {
            foreach ($list as $id=>$item) {
                foreach ($item['datastreams'] as $ds) {
                    $routeParams = array('id' => $item['id'], 'type' => $ds);
                    $outline['lists'][$key][$id][strtolower($ds)]
                        = $this->url->fromRoute('files', $routeParams);
                }
            }
        }
        return $outline;
    }
}
