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
    protected $datastreams = [];

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
     * Returns an array of classes for this object
     *
     * @param string $id record id
     *
     * @return array
     */
    public function getClasses($id)
    {
        $data = $this->getDatastreamContent($id, 'RELS-EXT');
        $matches = [];
        preg_match_all(
            '/rdf:resource="info:fedora\/vudl-system:([^"]+)/',
            $data,
            $matches
        );
        return $matches[1];
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $id  Record id
     * @param bool   $xml Return data as SimpleXMLElement?
     *
     * @return string|\SimpleXMLElement
     */
    public function getDatastreams($id, $xml = false)
    {
        if (!isset($this->datastreams[$id])) {
            $this->datastreams[$id] = $this->getDatastreamContent(
                $id,
                '/datastreams?format=xml',
                true
            );
        }
        if ($xml) {
            return simplexml_load_string($this->datastreams[$id]);
        } else {
            return $this->datastreams[$id];
        }
    }

    /**
     * Return the content of a datastream.
     *
     * @param string $id         Record id
     * @param string $stream     Name of stream to retrieve
     * @param bool   $justStream Do not append /content and return from url as is
     *
     * @return string
     */
    public function getDatastreamContent($id, $stream, $justStream = false)
    {
        if ($justStream) {
            $url = $this->getBase() . $id . $stream;
        } else {
            $url = $this->getBase() . $id . '/datastreams/' . $stream . '/content';
        }
        return file_get_contents($url);
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
     * Get details for the sidebar on a record.
     *
     * @param string $id     ID to retrieve
     * @param bool   $format Send result through formatDetails?
     *
     * @return string
     */
    public function getDetails($id, $format = false)
    {
        $dc = [];
        preg_match_all(
            '/<[^\/]*dc:([^ >]+)>([^<]+)/',
            $this->getDatastreamContent($id, 'DC'),
            $dc
        );
        $details = [];
        foreach ($dc[2] as $i => $detail) {
            $details[$dc[1][$i]] = $detail;
        }
        if ($format) {
            return $this->formatDetails($details);
        }
        return $details;
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
     * Get an item's label
     *
     * @param string $id Record's id
     *
     * @return string
     */
    public function getLabel($id)
    {
        $query = 'select $memberTitle from <#ri> '
            . 'where $member <dc:identifier> \'' . $id . '\' '
            . 'and $member <fedora-model:label> $memberTitle';
        $response = $this->query($query);
        $list = explode("\n", $response->getBody());
        return $list[1];
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
            . 'where $member <fedora-rels-ext:isMemberOf> <info:fedora/'
            . $root . '> '
            . 'and $member <fedora-model:label> $memberTitle '
            . 'and $member <dc:identifier> $memberPID';
        $response = $this->query($query);
        $list = explode("\n", $response->getBody());
        $items = [];
        for ($i = 1;$i < count($list);$i++) {
            if (empty($list[$i])) {
                continue;
            }
            list($id,) = explode(',', $list[$i], 2);
            $items[] = $id;
        }
        return $items;
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
        $query = 'select $lastModDate from <#ri> '
            . 'where $member '
            . '<info:fedora/fedora-system:def/view#lastModifiedDate> '
            . '$lastModDate '
            . 'and $member <dc:identifier> \'' . $id . '\'';
        $response = $this->query($query);
        $list = explode("\n", $response->getBody());
        return $list[1];
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $root record id
     *
     * @return array of ids
     */
    public function getOrderedMembers($root)
    {
        $query = 'select $memberPID $memberTitle $sequence $member from <#ri> '
            . 'where $member <fedora-rels-ext:isMemberOf> <info:fedora/'
            . $root . '> '
            . 'and $member <http://vudl.org/relationships#sequence> $sequence '
            . 'and $member <fedora-model:label> $memberTitle '
            . 'and $member <dc:identifier> $memberPID';
        $response = $this->query($query);
        $list = explode("\n", $response->getBody());
        if (count($list) > 2) {
            $items = [];
            $sequenced = true;
            for ($i = 1;$i < count($list);$i++) {
                if (empty($list[$i])) {
                    continue;
                }
                list($id, $title, $sequence,) = explode(',', $list[$i], 4);
                list($seqID, $seq) = explode('#', $sequence);
                if ($seqID != $root) {
                    $sequenced = false;
                    break;
                }
                $items[] = [
                    'seq' => $seq,
                    'id' => $id
                ];
            }
            if ($sequenced) {
                usort(
                    $items,
                    function ($a, $b) {
                        return intval($a['seq']) - intval($b['seq']);
                    }
                );
                return array_map(
                    function ($op) {
                        return $op['id'];
                    },
                    $items
                );
            }
        }
        // No sequence? Title sort.
        $query = 'select $memberPID $memberTitle from <#ri> '
            . 'where $member <fedora-rels-ext:isMemberOf> <info:fedora/'
            . $root . '> '
            . 'and $member <fedora-model:label> $memberTitle '
            . 'and $member <dc:identifier> $memberPID '
            . 'order by $memberTitle';
        $response = $this->query($query);
        $list = explode("\n", $response->getBody());
        $items = [];
        for ($i = 1;$i < count($list);$i++) {
            if (empty($list[$i])) {
                continue;
            }
            list($id, $title, ) = explode(',', $list[$i], 3);
            $items[] = $id;
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
        // Walk to get all parents to root
        $query = 'select $child $parent $parentTitle from <#ri> '
                . 'where walk ('
                        . '<info:fedora/' . $id . '> '
                        . '<fedora-rels-ext:isMemberOf> '
                        . '$parent '
                    . 'and $child <fedora-rels-ext:isMemberOf> $parent) '
                . 'and $parent <fedora-model:label> $parentTitle';
        // Parse out relationships
        $response = $this->query($query);
        $list = explode("\n", trim($response->getBody(), "\n"));
        $tree = [];
        for ($i = 1;$i < count($list);$i++) {
            list($child, $parent, $title) = explode(',', substr($list[$i], 12), 3);
            $parent = substr($parent, 12);
            if (!isset($tree[$parent])) {
                $tree[$parent] = [
                    'children' => [],
                    'title' => $title
                ];
            }
            $tree[$parent]['children'][] = $child;
        }
        $ret = $this->traceParents($tree, $id);
        // Store in cache
        $this->parentLists[$id] = $ret;
        return $ret;
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
     * Get collapsable XML for an id
     *
     * @param object        $record   Record data
     * @param View\Renderer $renderer View renderer to get techinfo template
     *
     * @return html string
     */
    public function getTechInfo($record = null, $renderer = null)
    {
        if ($record == null) {
            return false;
        }
        $ret = [];
        // OCR
        if (isset($record['ocr-dirty'])) {
            $record['ocr-dirty'] = htmlentities(
                $this->getDatastreamContent(
                    $record['id'],
                    'OCR-DIRTY'
                )
            );
        }
        // Technical Information
        if (isset($record['master-md'])) {
            $record['techinfo'] = $this->getDatastreamContent(
                $record['id'],
                'MASTER-MD'
            );
            $info = $this->getSizeAndTypeInfo($record['techinfo']);
            $ret['size']     = $info['size'];
            $ret['type'] = $info['type'];
        }
        if ($renderer != null) {
            $ret['div'] = $renderer
                ->render('vudl/techinfo.phtml', ['record' => $record]);
        }
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
        $data = $type = [];
        preg_match('/<size[^>]*>([^<]*)/', $techInfo, $data);
        preg_match('/mimetype="([^"]*)/', $techInfo, $type);
        $size_index = 0;
        if (count($data) > 1) {
            $bytes = intval($data[1]);
            $sizes = ['bytes','KB','MB'];
            while ($size_index < count($sizes) - 1 && $bytes > 1024) {
                $bytes /= 1024;
                $size_index++;
            }
            return [
                'size' => round($bytes, 1) . ' ' . $sizes[$size_index],
                'type' => $type[1]
            ];
        }
        return [];
    }

    /**
     * Get copyright URL and compare it to special cases from VuDL.ini
     *
     * @param array $id          record id
     * @param array $setLicenses ids are strings to match urls to,
     *  the values are abbreviations. Parsed in details.phtml later.
     *
     * @return array
     */
    public function getCopyright($id, $setLicenses)
    {
        $check = $this->getDatastreamHeaders($id, 'LICENSE');
        if (!strpos($check[0], '404')) {
            $xml = $this->getDatastreamContent($id, 'LICENSE');
            preg_match('/xlink:href="(.*?)"/', $xml, $license);
            $license = $license[1];
            foreach ($setLicenses as $tell => $value) {
                if (strpos($license, $tell)) {
                    return [$license, $value];
                }
            }
            return [$license, false];
        }
        return null;
    }

    /**
     * Consolidation of Zend Client calls
     *
     * @param string $query   Query for call
     * @param array  $options Additional options
     *
     * @return Response
     */
    protected function query($query, $options = [])
    {
        $data = [
            'type'  => 'tuples',
            'flush' => false,
            'lang'  => 'itql',
            'format' => 'CSV',
            'query' => $query
        ];
        foreach ($options as $key => $value) {
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
