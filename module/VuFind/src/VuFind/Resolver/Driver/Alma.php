<?php
/**
 * Alma Link Resolver Driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
namespace VuFind\Resolver\Driver;

/**
 * Alma Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Alma extends AbstractBase
{
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
     * @return string         Raw XML returned by resolver
     */
    public function fetchLinks($openURL)
    {
        // Make the call to Alma and load results
        $url = $this->getResolverUrl(
            'svc_dat=CTO&response_type=xml&' . $openURL
        );
        return $this->httpClient->setUri($url)->send()->getBody();
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

        foreach ($xml->context_services->children() as $service) {
            $record = [
                'title' => $this->getKeyWithId($service, 'package_public_name'),
                'href' => (string)$service->resolution_url,
                'service_type' => (string)$service->attributes()->service_type,
            ];
            if ($coverage = $this->getKeyWithId($service, 'Availability')) {
                $coverage = trim(str_replace('<br>', ' ', $coverage));
                $record['coverage'] = $coverage;
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Get a key with the specified id from the context_service element
     *
     * @param \SimpleXMLElement $service Service element
     * @param string            $id      Key id
     *
     * @return string
     */
    protected function getKeyWithId(\SimpleXMLElement $service, $id)
    {
        foreach ($service->keys->children() as $key) {
            if ((string)$key->attributes()->id === $id) {
                return (string)$key;
            }
        }
        return '';
    }
}
