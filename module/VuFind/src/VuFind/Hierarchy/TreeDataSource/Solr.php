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
use VuFindSearch\Backend\Solr\Connector;
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
     * Search service
     *
     * @var Connector
     */
    protected $solrConnector;

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
    protected $filters = [];

    /**
     * Constructor.
     *
     * @param SearchService $search   Search service
     * @param string        $cacheDir Directory to hold cache results (optional)
     * @param array         $filters  Filters to apply to Solr tree queries
     */
    public function __construct(SearchService $search, Connector $connector,
        $cacheDir = null, $filters = []
    ) {
        $this->searchService = $search;
        $this->solrConnector = $connector;
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
    public function getXML($id, $options = [])
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
     * @param string $count    The total count of items in the tree
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
            new ParamBag(['fq' => $this->filters, 'hl' => 'false'])
        );
        if ($results->getTotal() < 1) {
            return '';
        }
        $xml = [];
        $sorting = $this->getHierarchyDriver()->treeSorting();

        foreach ($results->getRecords() as $current) {
            ++$count;

            $titles = $titles = $current->getTitlesInHierarchy();
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

            // If we're in sorting mode, we need to create key-value arrays;
            // otherwise, we can just collect flat strings.
            if ($sorting) {
                $positions = $current->getHierarchyPositionsInParents();
                $sequence = isset($positions[$parentID]) ? $positions[$parentID] : 0;
                $xml[] = [$sequence, $xmlNode];
            } else {
                $xml[] = $xmlNode;
            }
        }

        // Assemble the XML, sorting it first if necessary:
        return implode('', $sorting ? $this->sortNodes($xml) : $xml);
    }

    /**
     * Get JSON for the specified hierarchy ID.
     *
     * Build the JSON file from the Solr fields
     *
     * @param string $id      Hierarchy ID.
     * @param array  $options Additional options for JSON generation.  (Currently one
     * option is supported: 'refresh' may be set to true to bypass caching).
     *
     * @return string
     */
    public function getJSON($id, $options = [])
    {
        $cacheFile = (null !== $this->cacheDir)
            ? $this->cacheDir . '/tree_' . urlencode($id) . '.json'
            : false;

        $useCache = isset($options['refresh']) ? !$options['refresh'] : true;
        $cacheTime = $this->getHierarchyDriver()->getTreeCacheTime();

        if ($useCache && file_exists($cacheFile)
            && ($cacheTime < 0 || filemtime($cacheFile) > (time() - $cacheTime))
        ) {
            $this->debug("Using cached data from $cacheFile");
            $json = file_get_contents($cacheFile);
            return $json;
        } else {
            $starttime = microtime(true);
            $query = new Query(
                'hierarchy_top_id:"' . addcslashes($id, '"') . '"'
            );
            $params = new ParamBag([
                'q'  => ['hierarchy_top_id:"' . $id . '"'],
                'fq' => $this->filters,
                'hl' => ['false'],
                'fl' => ['title, id, hierarchy_parent_id, hierarchy_top_id,'
                    . 'is_hierarchy_id, hierarchy_sequence, title_in_hierarchy'],
                'wt' => ['json'],
                'json.nl' => ['arrarr'],
                'rows' => [2147483647], // Integer max
                'start' => [0]
            ]);
            $response = $this->solrConnector->search($params);
            $results = json_decode($response);
            if ($results->response->numFound < 1) {
                return '';
            }
            $map = [$id => []];
            $this->debug('Making map... ' . abs(microtime(true) - $starttime));
            foreach ($results->response->docs as $current) {
                if (isset($current->hierarchy_parent_id)) {
                    foreach ($current->hierarchy_parent_id as $parentId) {
                        if (!isset($map[$parentId])) {
                            $map[$parentId] = [$current];
                        } else {
                            $map[$parentId][] = $current;
                        }
                    }
                }
            }
            $count = 0;
            // Get top record's info
            $params->set('q', ['id:"' . $id . '"']);
            $params->set('rows', [1]);
            $response = $this->solrConnector->search($params);
            $results = json_decode($response);
            $record = $results->response->docs[0];
            $this->debug($record->id);
            $json = $this->formatNodeJson($record);
            // Recursively build tree from hash
            $json->children = $this->mapToJSON($id, $map, $count);
            $encoded = json_encode($json, JSON_HEX_QUOT || JSON_HEX_APOS || JSON_HEX_AMP);

            $this->debug('Done: ' . abs(microtime(true) - $starttime));

            if ($cacheFile) {
                // Write file
                if (!file_exists($this->cacheDir)) {
                    mkdir($this->cacheDir);
                }
                file_put_contents($cacheFile, $encoded);
            }
            $this->debug(
                "Hierarchy of {$count} records built in " .
                abs(microtime(true) - $starttime)
            );
            return $encoded;
        }
    }

    /**
     * Get Solr Children for JSON
     *
     * @param string $parentID The starting point for the current recursion
     * (equivlent to Solr field hierarchy_parent_id)
     * @param string $count    The total count of items in the tree
     * before this recursion
     *
     * @return string
     */
    protected function formatNodeJson($record, $parentID = null)
    {
        $titles = $this->getTitlesInHierarchy($record);
        $title = isset($record->title) ? $record->title : $record->id;
        $title = null != $parentID && isset($titles[$parentID])
            ? $titles[$parentID] : $title;

        return (object) [
            'id' => $record->id,
            'type' => isset($record->is_hierarchy_id)
                ? 'collection'
                : 'record',
            'title' => htmlspecialchars($title)
        ];
    }

    /**
     * Get the positions of this item within parent collections.  Returns an array
     * of parent ID => sequence number.
     *
     * @return array
     */
    protected function getHierarchyPositionsInParents($fields)
    {
        $retVal = [];
        if (isset($fields->hierarchy_parent_id)
            && isset($fields->hierarchy_sequence)
        ) {
            foreach ($fields->hierarchy_parent_id as $key => $val) {
                $retVal[$val] = $fields->hierarchy_sequence[$key];
            }
        }
        return $retVal;
    }

     /**
     * Get the titles of this item within parent collections.  Returns an array
     * of parent ID => sequence number.
     *
     * @return Array
     */
    protected function getTitlesInHierarchy($fields)
    {
        $retVal = [];
        if (isset($fields->title_in_hierarchy)
            && is_array($fields->title_in_hierarchy)
        ) {
            $titles = $fields->title_in_hierarchy;
            $parentIDs = $fields->hierarchy_parent_id;
            if (count($titles) === count($parentIDs)) {
                foreach ($parentIDs as $key => $val) {
                    $retVal[$val] = $titles[$key];
                }
            }
        }
        return $retVal;
    }

    /**
     * Get Solr Children for JSON
     *
     * @param string $parentID The starting point for the current recursion
     * (equivlent to Solr field hierarchy_parent_id)
     * @param string $count    The total count of items in the tree
     * before this recursion
     *
     * @return string
     */
    protected function mapToJSON($parentID, $map, &$count)
    {
        $sorting = $this->getHierarchyDriver()->treeSorting();

        $json = [];
        foreach ($map[$parentID] as $current) {
            ++$count;

            $childNode = $this->formatNodeJson($current, $parentID);

            if (isset($map[$childNode->id])) {
                $children = $this->mapToJSON(
                    $current->id, $map, $count
                );
                if (!empty($children)) {
                    $childNode->children = $children;
                }
            }

            // If we're in sorting mode, we need to create key-value arrays;
            // otherwise, we can just collect flat values.
            if ($sorting) {
                $positions = $this->getHierarchyPositionsInParents($current);
                $sequence = isset($positions[$parentID]) ? $positions[$parentID] : 0;
                $json[] = [$sequence, $childNode];
            } else {
                $json[] = $childNode;
            }
        }

        return $sorting ? $this->sortNodes($json) : $json;
    }

    /**
     * Sort Nodes
     * Convert an unsorted array of [ key, value ] pairs into a sorted array
     * of values.
     *
     * @param array $array The array of arrays to sort
     *
     * @return array
     */
    protected function sortNodes($array)
    {
        // Sort arrays based on first element
        $sorter = function ($a, $b) {
            return strcmp($a[0], $b[0]);
        };
        usort($array, $sorter);

        // Collapse array to remove sort values
        $mapper = function ($i) {
            return $i[1];
        };
        return array_map($mapper, $array);
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
        $settings = $this->hierarchyDriver->getTreeSettings();

        if (!isset($settings['checkAvailability'])
            || $settings['checkAvailability'] == 1
        ) {
            $results = $this->searchService->retrieve(
                'Solr', $id, new ParamBag(['fq' => $this->filters])
            );
            if ($results->getTotal() < 1) {
                return false;
            }
        }
        // If we got this far the support-check was positive in any case.
        return true;
    }
}
