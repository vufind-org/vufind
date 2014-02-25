<?php
/**
 * VuDL to Fedora connection class (defines some methods to talk to Fedora)
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
class Fedora extends AbstractBase
{
    /**
     * Datastreams data cache
     *
     * @var array
     */
    protected $datastreams = array();

    /**
     * Get Fedora Base URL.
     *
     * @return string
     */
    public function getBase()
    {
        return isset($this->config->Fedora->url_base)
            ? $this->config->Fedora->url_base
            : null;
    }
    
    /**
     * Get Fedora Query URL.
     *
     * @return string
     */
    public function getQueryURL()
    {
        return isset($this->config->Fedora->query_url)
            ? $this->config->Fedora->query_url
            : null;
    }

    /**
     * Get details for the sidebar on a record.
     *
     * @param string $id ID to retrieve
     *
     * @return string
     */
    public function getDetails($id, $format = false)
    {
        $dc = array();
        preg_match_all(
            '/<[^\/]*dc:([^ >]+)>([^<]+)/',
            $this->getDatastreamContent($id, 'DC'),
            $dc
        );
        $details = array();
        foreach ($dc[2] as $i=>$detail) {
            $details[$dc[1][$i]] = $detail;
        }
        if ($format) {
            return $this->formatDetails($details);
        }
        return $details;
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
                    'q'  => $seqField.':[* TO *]',
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
            $response = $this->solr->search(
                new ParamBag(
                    array(
                        'q'  => 'relsext.isMemberOf:"'.$id.'"',
                        'wt' => 'json',
                        'rows' => 99999,
                        'fl' => 'id',
                        'group' => 'false'
                    )
                )
            );
            $data = json_decode($response);
            $structmap = array_map(
                function ($n) {
                    return $n->id;
                },
                $data->response->docs
            );
        } else {
            $structmap = array();
            foreach ($data->response->docs as $member) {
                $structmap[intval($member->$seqField)-1] = $member->id;
            }
        }
        // Reapply the global filters
        $map->setParameters('select', 'appends', $params->getArrayCopy());
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
        $query = 'select $memberPID $memberTitle from <#ri> '
            . 'where $member <fedora-rels-ext:isMemberOf> <info:fedora/' .$root. '> '
            . 'and $member <fedora-model:label> $memberTitle '
            . 'and $member <dc:identifier> $memberPID';
        $response = $this->query($query, array('format'=>'CSV'));
        $list = explode("\n", $response->getBody());
        $items = array();
        for ($i=1;$i<count($list);$i++) {
            if (empty($list[$i])) {
                continue;
            }
            list($id, $title) = explode(',', $list[$i], 2);
            $items[] = array(
                'id' => $id,
                'title' => trim($title, '"')
            );
        }
        return $items;
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
        $query = 'select $child $parent $parentTitle from <#ri> '
                . 'where walk ('
                        . '<info:fedora/' .$id. '> '
                        . '<fedora-rels-ext:isMemberOf> '
                        . '$parent '
                    . 'and $child <fedora-rels-ext:isMemberOf> $parent) '
                . 'and $parent <fedora-model:label> $parentTitle';
        $response = $this->query($query, array('format'=>'CSV'));
        $list = explode("\n", $response->getBody());
        $tree = array();
        $items = array();
        $roots = array();
        for ($i=1;$i<count($list);$i++) {
            if (empty($list[$i])) {
                continue;
            }
            list($child, $parent, $title) = explode(',', substr($list[$i], 12), 3);
            $parent = substr($parent, 12);
            if ($parent == $this->getRootId()) {
                $roots[] = $child;
                continue;
            }
            if ($child == $this->getRootId()) {
                continue;
            }
            if (isset($tree[$parent])) {
                $tree[$parent][] = $child;
            } else {
                $tree[$parent] = array($child);
            }
            $items[$parent] = str_replace('""', '"', trim($title, '" '));
        }
        $ret = array();
        $queue = array();
        foreach ($roots as $root) {
            $queue[] = array($root, array());
        }
        while ($path = array_pop($queue)) {
            $tid = $path[0];
            while ($tid != $id) {
                $path[1][$tid] = $items[$tid];
                for ($i=1;$i<count($tree[$tid]);$i++) {
                    $queue[] = array($tree[$tid][$i], $path[1]);
                }
                $tid = $tree[$tid][0];
            }
            $ret[] = array_reverse($path[1]);
        }
        //var_dump('---', $ret);
        $this->parentLists[$id] = $ret;
        return $ret;
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
        $data = file_get_contents(
            $this->getBase() . $id . '/datastreams/RELS-EXT/content'
        );
        $matches = array();
        preg_match_all(
            '/rdf:resource="info:fedora\/vudl-system:([^"]+)/',
            $data,
            $matches
        );
        return $matches[1];
    }

    /**
     * Return the object as XML.
     *
     * @param string $id Record id
     *
     * @return \SimpleXMLElement
     */
    public function getObjectAsXML($id)
    {
        return simplexml_load_file($this->getBase() . $id . '?format=xml');
    }

    /**
     * Return the content of a datastream.
     *
     * @param string $id     Record id
     * @param string $stream Name of stream to retrieve
     *
     * @return string
     */
    public function getDatastreamContent($id, $stream)
    {
        return file_get_contents(
            $this->getBase() . $id . '/datastreams/' . $stream . '/content'
        );
    }

    /**
     * Return the headers of a datastream.
     *
     * @param string $id     Record id
     * @param string $stream Name of stream to retrieve
     *
     * @return string
     */
    public function getDatastreamHeaders($id, $stream)
    {
        return get_headers(
            $this->getBase() . $id . '/datastreams/' . $stream . '/content'
        );
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string  $id  Record id
     * @param boolean $xml Return data as SimpleXMLElement?
     *
     * @return string|\SimpleXMLElement
     */
    public function getDatastreams($id, $xml = false)
    {
        if (!isset($this->datastreams[$id])) {
            $this->datastreams[$id] = file_get_contents(
                $this->getBase() . $id . '/datastreams?format=xml'
            );
        }
        if ($xml) {
            return simplexml_load_string($this->datastreams[$id]);
        } else {
            return $this->datastreams[$id];
        }
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to access
     *
     * @return \Zend\Http\Client
     */
    public function getHttpClient($url)
    {
        if ($this->httpService) {
            return $this->httpService->createClient($url);
        }
        return new \Zend\Http\Client($url);
    }

    /**
     * Consolidation of Zend Client calls
     *
     * @param string $query   Query for call
     * @param array  $options Additional options
     *
     * @return Response
     */
    protected function query($query, $options = array())
    {
        $data = array(
            'type'  => 'tuples',
            'flush' => false,
            'lang'  => 'itql',
            'format'=> 'Simple',
            'query' => $query
        );
        foreach ($options as $key=>$value) {
            $data[$key] = $value;
        }
        $client = $this->getHttpClient($this->getQueryURL());
        $client->setMethod('POST');
        $client->setAuth(
            $this->config->Fedora->adminUser, $this->config->Fedora->adminPass
        );
        $client->setParameterPost($data);
        return $client->send();
    }
}
