<?php
/**
 * SFX Link Resolver Driver
 *
 * PHP version 5
 *
 * Copyright (C) Royal Holloway, University of London
 *
 * last update: 2010-10-11
 * tested with X-Server SFX 3.2
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
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
namespace VuFind\Resolver\Driver;

/**
 * SFX Link Resolver Driver
 *
 * @category VuFind2
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
class Sfx implements DriverInterface
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
     * @param string            $baseUrl    Base URL for link resolver
     * @param \Zend\Http\Client $httpClient HTTP client
     */
    public function __construct($baseUrl, \Zend\Http\Client $httpClient)
    {
        $this->baseUrl = $baseUrl;
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
        // Make the call to SFX and load results
        $url = $this->baseUrl .
            '?sfx.response_type=multi_obj_detailed_xml&svc.fulltext=yes&' . $openURL;
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
        try {
            $xml = new \SimpleXmlElement($xmlstr);
        } catch (\Exception $e) {
            return $records;
        }

        $root = $xml->xpath("//ctx_obj_targets");
        $xml = $root[0];
        foreach ($xml->children() as $target) {
            $record = [];
            $record['title'] = (string)$target->target_public_name;
            $record['href'] = (string)$target->target_url;
            $record['service_type'] = (string)$target->service_type;
            if (isset($target->coverage->coverage_text)) {
                $coverageText = & $target->coverage->coverage_text;
                $record['coverage'] = (string)$coverageText
                    ->threshold_text->coverage_statement;
                if (isset($coverageText->embargo_text->embargo_statement)) {
                    $record['coverage'] .= ' ' . (string)$coverageText
                        ->embargo_text->embargo_statement;
                }
            }
            array_push($records, $record);
        }
        return $records;
    }
}