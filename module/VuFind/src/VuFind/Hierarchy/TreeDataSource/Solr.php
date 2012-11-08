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
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
namespace VuFind\Hierarchy\TreeDataSource;
use VuFind\Connection\Manager as ConnectionManager;

/**
 * Hierarchy Tree Data Source (Solr)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind2
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class Solr extends AbstractBase
{
    /**
     * Solr connection
     *
     * @var object
     */
    protected $db;

    /**
     * Cache directory
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * Constructor.
     *
     * @param string $cacheDir Directory to use for caching results (optional)
     */
    public function __construct($cacheDir = null)
    {
        $this->db = ConnectionManager::connectToIndex();
        $this->cacheDir = rtrim($cacheDir, '/');
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
        $top = $this->db->getRecord($id);
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
            $xml = '<root><item id="' .
                htmlspecialchars($id) .
                '">' .
                '<content><name>' . htmlspecialchars($top['title']) .
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
        $query = 'hierarchy_parent_id:"' . addcslashes($parentID, '"') . '"';
        $results = $this->db->search(array('query' => $query, 'limit' => 10000));
        if ($results === false) {
            return '';
        }
        $xml = array();
        $sorting = $this->getHierarchyDriver()->treeSorting();

        foreach ($results['response']['docs'] as $doc) {
            ++$count;
            if ($sorting) {
                foreach ($doc['hierarchy_parent_id'] as $key => $val) {
                    if ($val == $parentID) {
                        $sequence = $doc['hierarchy_sequence'][$key];
                    }
                }
            }

            $this->debug("$parentID: " . $doc['id']);
            $xmlNode = '';
            $xmlNode .= '<item id="' . htmlspecialchars($doc['id']) .
                '"><content><name>' .
                htmlspecialchars($doc['title_full']) . '</name></content>';
            $xmlNode .= $this->getChildren($doc['id'], $count);
            $xmlNode .= '</item>';
            array_push($xml, array((isset($sequence)?$sequence: 0),$xmlNode));
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
