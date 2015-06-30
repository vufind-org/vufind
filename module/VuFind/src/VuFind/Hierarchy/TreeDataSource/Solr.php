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
use VuFind\Hierarchy\TreeDataFormatter\PluginManager as FormatterManager;
use VuFindSearch\Query\Query;
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
     * @var Connector
     */
    protected $solrConnector;

    /**
     * Formatter manager
     *
     * @var FormatterManager
     */
    protected $formatterManager;

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
     * @param Connector        $connector Solr connector
     * @param FormatterManager $fm        Formatter manager
     * @param string           $cacheDir  Directory to hold cache results (optional)
     * @param array            $filters   Filters to apply to Solr tree queries
     */
    public function __construct(Connector $connector, FormatterManager $fm,
        $cacheDir = null, $filters = []
    ) {
        $this->solrConnector = $connector;
        $this->formatterManager = $fm;
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
        return $this->getFormattedData($id, 'xml', $options, 'hierarchyTree_%s.xml');
    }

    /**
     * Search Solr.
     *
     * @param string $q    Search query
     * @param int    $rows Max rows to retrieve (default = int max)
     *
     * @return array
     */
    protected function searchSolr($q, $rows = 2147483647)
    {
        $params = new ParamBag(
            [
                'q'  => [$q],
                'fq' => $this->filters,
                'hl' => ['false'],
                'fl' => ['title,id,hierarchy_parent_id,hierarchy_top_id,'
                    . 'is_hierarchy_id,hierarchy_sequence,title_in_hierarchy'],
                'wt' => ['json'],
                'json.nl' => ['arrarr'],
                'rows' => [$rows], // Integer max
                'start' => [0]
            ]
        );
        $response = $this->solrConnector->search($params);
        return json_decode($response);
    }

    /**
     * Retrieve a map of children for the provided hierarchy.
     *
     * @param string $id Record ID
     *
     * @return array
     */
    protected function getMapForHierarchy($id)
    {
        // Static cache of last map; if the user requests the same map twice
        // in a row (as when generating XML and JSON in sequence) this will
        // save a Solr hit.
        static $map;
        static $lastId = null;
        if ($id === $lastId) {
            return $map;
        }
        $lastId = $id;

        $results = $this->searchSolr('hierarchy_top_id:"' . $id . '"');
        if ($results->response->numFound < 1) {
            return [];
        }
        $map = [$id => []];
        foreach ($results->response->docs as $current) {
            $parents = isset($current->hierarchy_parent_id)
                ? $current->hierarchy_parent_id : [];
            foreach ($parents as $parentId) {
                if (!isset($map[$parentId])) {
                    $map[$parentId] = [$current];
                } else {
                    $map[$parentId][] = $current;
                }
            }
        }
        return $map;
    }

    /**
     * Get a record from Solr (return false if not found).
     *
     * @param string $id ID to fetch.
     *
     * @return array|bool
     */
    protected function getRecord($id)
    {
        // Static cache of last record; if the user requests the same map twice
        // in a row (as when generating XML and JSON in sequence) this will
        // save a Solr hit.
        static $record;
        static $lastId = null;
        if ($id === $lastId) {
            return $record;
        }
        $lastId = $id;

        $recordResults = $this->searchSolr('id:"' . $id . '"', 1);
        $record = isset($recordResults->response->docs[0])
            ? $recordResults->response->docs[0] : false;
        return $record;
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
        return $this->getFormattedData($id, 'json', $options, 'tree_%s.json');
    }

    /**
     * Get formatted data for the specified hierarchy ID.
     *
     * @param string $id            Hierarchy ID.
     * @param string $format        Name of formatter service to use.
     * @param array  $options       Additional options for JSON generation.
     * (Currently one option is supported: 'refresh' may be set to true to
     * bypass caching).
     * @param string $cacheTemplate Template for cache filenames
     *
     * @return string
     */
    public function getFormattedData($id, $format, $options = [],
        $cacheTemplate = 'tree_%s'
    ) {
        $cacheFile = (null !== $this->cacheDir)
            ? $this->cacheDir . '/' . sprintf($cacheTemplate, urlencode($id))
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
            $map = $this->getMapForHierarchy($id);
            if (empty($map)) {
                return '';
            }
            // Get top record's info
            $formatter = $this->formatterManager->get($format);
            $formatter->setRawData(
                $this->getRecord($id), $map,
                $this->getHierarchyDriver()->treeSorting(),
                $this->getHierarchyDriver()->getCollectionLinkType()
            );
            $encoded = $formatter->getData();
            $count = $formatter->getCount();

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
            if (!$this->getRecord($id)) {
                return false;
            }
        }
        // If we got this far the support-check was positive in any case.
        return true;
    }
}
