<?php
/**
 * VuDL to Solr connection class (defines some methods to talk to Solr)
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
namespace VuDL\Connection;
use VuFindHttp\HttpServiceInterface,
    VuFindSearch\ParamBag;

/**
 * VuDL-Fedora connection class
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class Solr extends AbstractBase
{
    /**
     * Connector class to Solr
     *
     * @var array
     */
    protected $solr;
    protected $sm;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config           $config  config
     * @param \VuFind\Search\BackendManager $backend backend manager
     */
    public function __construct($config, $backend)
    {
        $this->config = $config;
        $this->solr = $backend->getConnector();
    }

    /**
     * Get details from Solr
     *
     * @param string  $id     ID to look up
     * @param boolean $format Send result through formatDetails?
     *
     * @return array
     * @throws \Exception
     */
    public function getDetails($id, $format)
    {
        // Remove global filters from the connector
        $map = $this->solr->getMap();
        $params = $map->getParameters('select', 'appends');
        $map->setParameters('select', 'appends', array());
        $details = null;
        if ($response = $this->solr->search(
            new ParamBag(
                array(
                    'q'     => 'id:"'.$id.'"',
                    'wt'    => 'json'
                )
            )
        )) {
            $record = json_decode($response);
            if ($format) {
                $details = $this->formatDetails((Array) $record->response->docs[0]);
            }
            $details = (Array) $record->response->docs[0];
        }
        // Reapply the global filters
        $map->setParameters('select', 'appends', $params->getArrayCopy());
        return null;
    }
    
    /**
     * Get the last modified date from Solr
     *
     * @param string $id ID to look up
     *
     * @return array
     * @throws \Exception
     */
    public function getModDate($id)
    {
        $modfield = 'fgs.lastModifiedDate';
        if ($response = $this->solr->search(
            new ParamBag(
                array(
                    'q'     => 'id:"'.$id.'"',
                    'wt'    => 'json',
                    'group' => 'false',
                    'fl'    => $modfield
                )
            )
        )) {
            $record = json_decode($response);
            return $record->response->docs[0]->$modfield;
        }
        return null;
    }
    
    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $id record id
     *
     * @return string $id
     */
    public function getOrderedMembers($id)
    {
        // Remove global filters from the connector
        $map = $this->solr->getMap();
        $params = $map->getParameters('select', 'appends');
        $map->setParameters('select', 'appends', array());
        // Try to find members in order
        $seqField = 'sequence_'.str_replace(':', '_', $id).'_str';
        $response = $this->solr->search(
            new ParamBag(
                array(
                    'q'  => 'relsext.isMemberOf:"'.$id.'"',
                    'sort'  => $seqField.' asc',
                    'wt' => 'json',
                    'rows' => 99999,
                    'fl' => 'id,'.$seqField,
                    'group' => 'false'
                )
            )
        );
        $data = json_decode($response);
        // If we didn't find anything, do a standard members search
        if ($data->response->numFound == 0) {
            return null;
        } else {
            $structmap = array_map(
                function ($n) {
                    return $n->id;
                },
                $data->response->docs
            );
        }
        // Reapply the global filters
        $map->setParameters('select', 'appends', $params->getArrayCopy());
        //var_dump($structmap);
        return $structmap;
    }

    /**
     * Tuple call to return and parse a list of members...
     *
     * @param string $root ...for this id
     *
     * @return array of members in order
     */
    public function getMemberList($root)
    {
        // Remove global filters from the connector
        $map = $this->solr->getMap();
        $params = $map->getParameters('select', 'appends');
        $map->setParameters('select', 'appends', array());
        // Get members
        $response = $this->solr->search(
            new ParamBag(
                array(
                    'q'  => 'relsext.isMemberOf:"'.$root.'"',
                    'wt' => 'json',
                    'rows' => 100,
                    'fl' => 'id,hierarchy_top_title',
                    'group' => 'false'
                )
            )
        );
        $children = json_decode($response);
        // Reapply the global filters
        $map->setParameters('select', 'appends', $params->getArrayCopy());
        // If we have results
        if ($children->response->numFound > 0) {
            return array_map(
                function ($n) {
                    return array(
                        'id' => $n->id,
                        'title' => $n->hierarchy_top_title,
                    );
                },
                $children->response->docs
            );
        }
        return array();
    }

    /**
     * Tuple call to return and parse a list of parents...
     *
     * @param string $id ...for this id
     *
     * @return array of parents in order from top-down
     */
    public function getParentList($id)
    {
        if (isset($this->parentLists[$id])) {
            return $this->parentLists[$id];
        }
        // Solr        
        // Get members
        $origin = $this->solr->search(
            new ParamBag(
                array(
                    'q'     => 'id:"'.$id.'"',
                    'fl'    => 'hierarchy_all_parents_str_mv,'
                        . 'hierarchy_top_id,'
                        . 'title_short,'
                        . 'hierarchy_parent_id,'
                        . 'hierarchy_parent_title',
                    'wt'    => 'json',
                    'group' => 'false'
                )
            )
        );
        $origin = json_decode($origin);
        $top = $origin->response->docs[0]->hierarchy_top_id;
        // If we have results, find the structure
        if ($origin->response->numFound > 0) {
            $parents = array_unique(
                $origin->response->docs[0]->hierarchy_all_parents_str_mv
            );
            $ret = array();
            $hierarchyParents = $origin->response->docs[0]->hierarchy_parent_id;
            foreach ($hierarchyParents as $i=>$parent) {
                $ret[] = array(
                    $origin->response->docs[0]->hierarchy_parent_id[$i]
                        => $origin->response->docs[0]->hierarchy_parent_title[$i]
                );
            }
            $current = 0;
            $last = key($ret[0]);
            $limit = 50;
            while ($limit-- && $current < count($ret)) {
                $path = $ret[$current];
                $partOf = $this->solr->search(
                    new ParamBag(
                        array(
                            'q'     => 'id:"'.$last.'"',
                            'fl'    => 'hierarchy_top_id,'
                                . 'hierarchy_parent_id,'
                                . 'hierarchy_parent_title',
                            'wt'    => 'json',
                            'group' => 'false',
                        )
                    )
                );
                $partOf = json_decode($partOf);
                $parentIDs = $partOf->response->docs[0]->hierarchy_parent_id;
                $parentTitles = $partOf->response->docs[0]->hierarchy_parent_title;
                $topIDs = $partOf->response->docs[0]->hierarchy_top_id;
                $count = 0;
                foreach ($parentIDs as $i=>$pid) {
                    $ptitle = trim($parentTitles[$i]);
                    if (in_array($pid, $parents)) {
                        if ($count == 0) {
                            $ret[$current][$pid] = $ptitle;
                            if (in_array($pid, $top)) {
                                $current ++;
                                if ($current == count($ret)) {
                                    break 2;
                                }
                                end($ret[$current]);
                                $last = key($ret[$current]);
                            } else {
                                foreach ($topIDs as $tid) {
                                    if (!in_array($tid, $top)) {
                                        $top[] = $tid;
                                    }
                                }
                                $last = $pid;
                            }
                        } else {
                            $path2 = $path;
                            $path2[$pid] = $ptitle;
                            $ret[] = $path2;
                        }                      
                        $count ++;
                    }
                }
            }
            $this->parentLists[$id] = $ret;
            return $ret;
        }
        return null;
    }
    
    /**
     * Returns an array of classes for this object
     *
     * @param string $id record id
     *
     * @return array
     */
    public function getClasses($id)
    {
        // Get record
        $response = $this->solr->search(
            new ParamBag(
                array(
                    'q'  => 'id:"'.$id.'"',
                    'wt' => 'json',
                    'fl' => 'modeltype_str_mv',
                    'group' => 'false'
                )
            )
        );
        $record = json_decode($response);
        if ($record->response->numFound > 0) {
            return array_map(
                function ($op) {
                    return substr($op, 12);
                },
                $record->response->docs[0]->modeltype_str_mv
            );
        }
        return null;
    }
}
