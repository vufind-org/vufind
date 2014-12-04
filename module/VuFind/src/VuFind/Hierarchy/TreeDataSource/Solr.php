<?php
/**
 * Hierarchy Tree Data Source (Solr)
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
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeDataSource;
use VuFindSearch\Query\Query;
use VuFindSearch\Service as SearchService;
use VuFindSearch\ParamBag;

/**
 * Hierarchy Tree Data Source (Solr)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind2
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Solr extends AbstractBase
{
    /**
     * Search service
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Cache directory
     *
     * @var string
     */
    protected $cacheDir = null;

    /**
     * Filter queries
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Constructor.
     *
     * @param SearchService $search   Search service
     * @param string        $cacheDir Directory to hold cache results (optional)
     * @param array         $filters  Filters to apply to Solr tree queries
     */
    public function __construct(SearchService $search, $cacheDir = null,
        $filters = array()
    ) {
        $this->searchService = $search;
        if (null !== $cacheDir) {
            $this->cacheDir = rtrim($cacheDir, '/');
        }
        $this->filters = $filters;
    }

    /**
     * Get XML for the specified hierarchy ID.
     *
     * Build the XML file from the Solr fields
     *
     * @param string $id      Hierarchy ID.
     * @param array  $options Additional options for XML generation.  (Currently one
     * option is supported: 'refresh' may be set to true to bypass caching).
     *
     * @return string
     */
    public function getXML($id, $options = array())
    {
        $top = $this->searchService->retrieve('Solr', $id)->getRecords();
        if (!isset($top[0])) {
            return '';
        }
        $top = $top[0];
        $cacheFile = (null !== $this->cacheDir)
            ? $this->cacheDir . '/hierarchyTree_' . urlencode($id) . '.xml'
            : false;

        $useCache = isset($options['refresh']) ? !$options['refresh'] : true;
        $cacheTime = $this->getHierarchyDriver()->getTreeCacheTime();

        if ($useCache && file_exists($cacheFile)
            && ($cacheTime < 0 || filemtime($cacheFile) > (time() - $cacheTime))
        ) {
            $this->debug("Using cached data from $cacheFile");
            $xml = file_get_contents($cacheFile);
        } else {
            $starttime = microtime(true);
            $isCollection = $top->isCollection() ? "true" : "false";
            $xml = '<root><item id="' .
                htmlspecialchars($id) .
                '" isCollection="' . $isCollection . '">' .
                '<content><name>' . htmlspecialchars($top->getTitle()) .
                '</name></content>';
            $count = 0;
            $xml .= $this->getChildren($id, $count);
            $xml .= '</item></root>';
            if ($cacheFile) {
                if (!file_exists($this->cacheDir)) {
                    mkdir($this->cacheDir);
                }
                file_put_contents($cacheFile, $xml);
            }
            $this->debug(
                "Hierarchy of $count records built in " .
                abs(microtime(true) - $starttime)
            );
        }
        return $xml;
    }

    /**
     * Get Solr Children
     *
     * @param string $parentID The starting point for the current recursion
     * (equivlent to Solr field hierarchy_parent_id)
     * @param string &$count   The total count of items in the tree
     * before this recursion
     *
     * @return string
     */
    protected function getChildren($parentID, &$count)
    {
        $query = new Query(
            'hierarchy_parent_id:"' . addcslashes($parentID, '"') . '"'
        );
        $results = $this->searchService->search(
            'Solr', $query, 0, 10000,
            new ParamBag(array('fq' => $this->filters, 'hl' => 'false'))
        );
        if ($results->getTotal() < 1) {
            return '';
        }
        $xml = array();
        $sorting = $this->getHierarchyDriver()->treeSorting();

        foreach ($results->getRecords() as $current) {
            ++$count;
            if ($sorting) {
                $positions = $current->getHierarchyPositionsInParents();
                if (isset($positions[$parentID])) {
                    $sequence = $positions[$parentID];
                }
            }

            $titles = $current->getTitlesInHierarchy();
            $title = isset($titles[$parentID])
                ? $titles[$parentID] : $current->getTitle();

            $this->debug("$parentID: " . $current->getUniqueID());
            $xmlNode = '';
            $isCollection = $current->isCollection() ? "true" : "false";
            $xmlNode .= '<item id="' . htmlspecialchars($current->getUniqueID()) .
                '" isCollection="' . $isCollection . '"><content><name>' .
                htmlspecialchars($title) . '</name></content>';
            $xmlNode .= $this->getChildren($current->getUniqueID(), $count);
            $xmlNode .= '</item>';
            array_push($xml, array((isset($sequence) ? $sequence : 0), $xmlNode));
        }

        if ($sorting) {
            $this->sortNodes($xml, 0);
        }

        $xmlReturnString = '';
        foreach ($xml as $node) {
            $xmlReturnString .= $node[1];
        }
        return $xmlReturnString;
    }

    /**
     * Sort Nodes
     *
     * @param array  &$array The Array to Sort
     * @param string $key    The key to sort on
     *
     * @return void
     */
    protected function sortNodes(&$array, $key)
    {
        $sorter=array();
        $ret=array();
        reset($array);
        foreach ($array as $ii => $va) {
            $sorter[$ii]=$va[$key];
        }
        asort($sorter);
        foreach ($sorter as $ii => $va) {
            $ret[$ii]=$array[$ii];
        }
        $array=$ret;
    }

    /**
     * Does this data source support the specified hierarchy ID?
     *
     * @param string $id Hierarchy ID.
     *
     * @return bool
     */
    public function supports($id)
    {
        // Assume all IDs are supported.
        return true;
    }
}
