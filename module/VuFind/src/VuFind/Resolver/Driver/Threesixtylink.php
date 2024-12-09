<?php

/**
 * 360Link Link Resolver Driver
 *
 * PHP version 8
 *
 * Copyright (C) Royal Holloway, University of London
 *
 * last update: 2010-11-17
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver\Driver;

use DOMDocument;
use DOMXpath;

/**
 * 360Link Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Threesixtylink extends AbstractBase
{
    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
     */
    protected $httpClient;

    /**
     * Constructor
     *
     * @param string               $baseUrl    Base URL for link resolver
     * @param \Laminas\Http\Client $httpClient HTTP client
     */
    public function __construct($baseUrl, \Laminas\Http\Client $httpClient)
    {
        parent::__construct($baseUrl);
        $this->httpClient = $httpClient;
    }

    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string         raw XML returned by resolver
     */
    public function fetchLinks($openURL)
    {
        // Make the call to SerialsSolutions and load results
        $url = $this->baseUrl . (str_ends_with($this->baseUrl, '/') ? '' : '/') .
            'openurlxml?version=1.0&' . $openURL;
        $feed = $this->httpClient->setUri($url)->send()->getBody();
        return $feed;
    }

    /**
     * Parse Links
     *
     * Parses an XML file returned by a link resolver
     * and converts it to a standardised format for display
     *
     * @param string $xmlstr Raw XML returned by resolver
     *
     * @return array         Array of values
     */
    public function parseLinks($xmlstr)
    {
        $records = []; // array to return

        $xml = new DOMDocument();
        if (!@$xml->loadXML($xmlstr)) {
            return $records;
        }

        $xpath = new DOMXpath($xml);
        $linkGroups = $xpath->query("//ssopenurl:linkGroup[@type='holding']");
        if (null !== $linkGroups) {
            foreach ($linkGroups as $linkGroup) {
                $record = [];
                // select the deepest link returned
                $elems = $xpath->query(
                    ".//ssopenurl:url[@type='article']",
                    $linkGroup
                );
                if ($elems->length > 0) {
                    $record['linktype'] = 'article';
                } else {
                    $elems = $xpath->query(
                        ".//ssopenurl:url[@type='journal']",
                        $linkGroup
                    );
                    if ($elems->length > 0) {
                        $record['linktype'] = 'journal';
                    } else {
                        $elems = $xpath->query(
                            ".//ssopenurl:url[@type='source']",
                            $linkGroup
                        );
                        if ($elems->length > 0) {
                            $record['linktype'] = 'source';
                        }
                    }
                }
                if ($elems->length > 0) {
                    $href = $elems->item(0)->nodeValue;
                    $record['href'] = $href;
                    $record['service_type'] = 'getFullTxt';
                } else {
                    $record['service_type'] = 'getHolding';
                }
                $elems = $xpath->query(
                    './/ssopenurl:holdingData/ssopenurl:providerName',
                    $linkGroup
                );
                $title = $elems->item(0)->textContent;
                $elems = $xpath->query(
                    './/ssopenurl:holdingData/ssopenurl:databaseName',
                    $linkGroup
                );
                $title .= ' - ' . $elems->item(0)->textContent;
                $record['title'] = $title;
                $elems = $xpath->query(
                    './/ssopenurl:holdingData/ssopenurl:startDate',
                    $linkGroup
                );
                if ($elems->length > 0) {
                    $record['coverage'] = $elems->item(0)->textContent . ' - ';
                }
                $elems = $xpath->query(
                    './/ssopenurl:holdingData/ssopenurl:endDate',
                    $linkGroup
                );
                if ($elems->length > 0) {
                    $record['coverage'] .= $elems->item(0)->textContent;
                }

                array_push($records, $record);
            }
        }
        return $records;
    }
}
