<?php
/**
 * WorldCat Knowledge BaseLink Resolver Driver
 *
 * PHP version 5
 *
 * Copyright (C) OCLC
 *
 * last update: 2015-05-22
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
 * @package  Resolver_Drivers
 * @author   Karen Coombs <coombsk@oclc.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
namespace VuFind\Resolver\Driver;

/**
 * WorldCat Knowledge Base Link Resolver Driver
 *
 * @category VuFind2
 * @package  Resolver_Drivers
 * @author   Karen Coombs <coombsk@oclc.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
class WorldCatKnowledgeBase implements DriverInterface
{
    /**
     * Base URL for link resolver
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $httpClient;

    /**
     * Constructor
     *
     * @param array            $baseUrl    The OpenURL Configuration
     * @param \Zend\Http\Client $httpClient HTTP client
     */
    public function __construct($openURLConfig, \Zend\Http\Client $httpClient)
    {
        $this->baseUrl = $openURLConfig->url;
        $this->wskey = $openURLConfig->worldcatKnowledgeBaseWskey;
        $this->httpClient = $httpClient;
    }

    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string         raw JSON returned by resolver
     */
    public function fetchLinks($openURL)
    {
 		$kbrequest = "http://worldcat.org/webservices/kb/openurl/resolve?" . $openURL;
    	$kbrequest .= '&wskey=' . $this->wskey;
    	 
    	$this->httpClient->setUri($kbrequest);
    	$adapter = new \Zend\Http\Client\Adapter\Curl();
    	$this->httpClient->setAdapter($adapter);
    	$result = $this->httpClient->setMethod('GET')->send();
    	
    	if ($result->isSuccess()){
    		return $result->getBody();
    	} else {
    		throw new \Exception('WorldCat Knowledge Base API error - ' . $result->getStatusCode() . ' - ' . $result->getReasonPhrase());
    	}
    }

    /**
     * Parse Links
     *
     * Parses an JSON file returned by a link resolver
     * and converts it to a standardised format for display
     *
     * @param string $jsonstr Raw JSON returned by resolver
     *
     * @return array         Array of values
     */
    public function parseLinks($jsonstr)
    {
        $records = []; // array to return
        try {
        	$kbresponse = json_decode($jsonstr, true);
        	foreach ($kbresponse as $item) {
        		$record = [];
        		$record['title'] =  $item['collection_name'];
        		$record['href'] =  $item['url'];
        		if ($item['content'] == "fulltext"){
        			$record['service_type'] = "getFulltext";
        		} elseif ($item['content'] == "ebook"){
        			$record['service_type'] = "getFulltext";
        		} elseif($item['content'] == "print"){
        			$record['service_type'] = "getHolding";
        		}else{
        			// selectedft, abstracts, indexed
        			$record['service_type'] = "getWebService";
        		}
        		if ($item['content'] == "fulltext" || $item['content'] == "print"){
        			$record['coverage'] = $item['coverage'];
        		}
        		array_push($records, $record);
        	}
        } catch (\Exception $e) {
            return $records;
        }
                
        return $records;
    }
}