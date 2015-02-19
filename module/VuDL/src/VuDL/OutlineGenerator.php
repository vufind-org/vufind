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
 * @package  VuDL
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
 * @package  VuDL
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class OutlineGenerator
{
    /**
     * VuDL connection manager
     *
     * @var connector
     */
    protected $connector;

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
     * Queues
     *
     * @var array
     */
    protected $queue;

    /**
     * Modification date information
     *
     * @var array
     */
    protected $moddate;

    /**
     * Outline currently under construction
     *
     * @var array
     */
    protected $outline;

    /**
     * Constructor
     *
     * @param Fedora      $connector VuDL connection manager
     * @param UrlHelper   $url       URL helper
     * @param array       $routes    VuDL route configuration
     * @param object|bool $cache     Cache object (or false to disable caching)
     */
    public function __construct(Connection\Manager $connector, UrlHelper $url,
        $routes = [], $cache = false
    ) {
        $this->connector = $connector;
        $this->url = $url;
        $this->routes = $routes;
        $this->cache = $cache;
    }

    /**
     * Compares the cache date against a given date. If given date is newer,
     * return false in order to refresh cache. Else return cache!
     *
     * @param string      $key     Unique key of cache object
     * @param string|Date $moddate Date to test cache time freshness
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
     * @param string $key  Unique key of cache object
     * @param object $data Object to save
     *
     * @return cache object or false
     */
    protected function setCache($key, $data)
    {
        if ($this->cache) {
            $this->cache->setItem(
                $key,
                [
                    'moddate' => date(DATE_ATOM),
                    'outline' => $data
                ]
            );
            return $data;
        }
        return false;
    }

    /**
     * Load information on lists within the specified record.
     *
     * @param string $root record ID
     *
     * @return void
     */
    protected function loadLists($root)
    {
        // Reset the state of the class:
        $this->queue = $this->moddate = [];
        $this->outline = [
            'counts' => [],
            'names' => [],
            'lists' => []
        ];
        // Get lists
        $lists = $this->connector->getOrderedMembers($root);
        // Get list items
        foreach ($lists as $i => $list_id) {
            // Get list name
            $this->outline['names'][] = $this->connector->getLabel($list_id);
            $this->moddate[$i] = $this->connector->getModDate($list_id);
            $items = $this->connector->getOrderedMembers($list_id);
            $this->queue[$i] = $items;
        }
    }

    /**
     * Build a single item within the outline.
     *
     * @param string $id ID of record to retrieve
     *
     * @return array
     */
    protected function buildItem($id)
    {
        // Else, get all the data and save it to the cache
        $list = [];
        // Get the file type
        $file = $this->connector->getDatastreams($id);
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
        $details = $this->connector->getDetails($id, false);
        return [
            'id' => $id,
            'fulltype' => $type,
            'mimetype' => $mimetype,
            'filetype' => isset($this->routes[$type])
                ? $this->routes[$type]
                : $type,
            'label' => isset($details['title'])
                ? $details['title']
                : $id,
            'datastreams' => $list[1],
            'mimetypes' => $list[2]
        ];
    }

    /**
     * Load all pages and docs.
     *
     * @param string $start      page/doc to start with for the return
     * @param int    $pageLength page length (leave null to use default)
     *
     * @return void
     */
    protected function loadPagesAndDocs($start, $pageLength)
    {
        // Set default page length if necessary
        if ($pageLength == null) {
            $pageLength = $this->connector->getPageLength();
        }

        // Get data on all pages and docs
        foreach ($this->queue as $parent => $items) {
            $this->outline['counts'][$parent] = count($items);
            if (count($items) < $start) {
                continue;
            }
            $this->outline['lists'][$parent] = [];
            for ($i = $start;$i < $start + $pageLength;$i++) {
                if ($i >= count($items)) {
                    break;
                }
                $id = $items[$i];
                // If there's a cache of this page...
                $item = $this->getCache(md5($id), $this->moddate[$parent]);
                if (!$item) {
                    $item = $this->buildItem($id);
                    $this->setCache(md5($id), $item);
                }
                $this->outline['lists'][$parent][$i] = $item;
            }
        }
    }

    /**
     * Add URLs to the outline.
     *
     * @return void
     */
    protected function injectUrls()
    {
        foreach ($this->outline['lists'] as $key => $list) {
            foreach ($list as $id => $item) {
                foreach ($item['datastreams'] as $ds) {
                    $routeParams = ['id' => $item['id'], 'type' => $ds];
                    $this->outline['lists'][$key][$id][strtolower($ds)]
                        = $this->url->fromRoute('files', $routeParams);
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
    public function getOutline($root, $start = 0, $pageLength = null)
    {
        $this->loadLists($root);
        $this->loadPagesAndDocs($start, $pageLength);
        $this->injectUrls();
        return $this->outline;
    }
}
